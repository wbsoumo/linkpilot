import os
import json
import logging
import re
import asyncio
from typing import Dict, Any, Optional
from fastapi import FastAPI, HTTPException, Request, Header
from fastapi.responses import JSONResponse
from pydantic import BaseModel, HttpUrl
from playwright.async_api import async_playwright

# Setup logging
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("linkedin_scraper_service")

app = FastAPI(title="LinkPilot LinkedIn Scraper Microservice", version="1.0.0")

# Security API Key config
API_KEY = os.getenv("SCRAPER_API_KEY", "linkpilot_local_scraper_secret_2026")
SESSION_FILE = os.path.join(os.path.dirname(__file__), "session.json")

class ScrapeRequest(BaseModel):
    linkedin_url: str

def validate_linkedin_url(url: str) -> bool:
    # Match standard linkedin profile URLs
    pattern = r"^https:\/\/(www\.)?linkedin\.com\/in\/[a-zA-Z0-9\-_%]+\/?$"
    return bool(re.match(pattern, url.split('?')[0]))

@app.middleware("http")
async def restrict_access(request: Request, call_next):
    # Allow local requests or verify X-API-Key
    client_host = request.client.host if request.client else ""
    api_key_header = request.headers.get("X-API-Key")
    
    if client_host not in ("127.0.0.1", "localhost") and api_key_header != API_KEY:
        logger.warning(f"Unauthorized access attempt from host {client_host}")
        return JSONResponse(status_code=401, content={"success": False, "message": "Unauthorized access."})
    
    return await call_next(request)

@app.post("/scrape")
async def scrape_profile(req: ScrapeRequest) -> Dict[str, Any]:
    url = req.linkedin_url.strip()
    # Normalize URL
    url = url.split('?')[0]
    if not url.endswith('/'):
        url += '/'
        
    if not validate_linkedin_url(url):
        logger.error(f"Invalid LinkedIn URL: {url}")
        return {"success": False, "message": "Invalid LinkedIn Profile URL."}
        
    if not os.path.exists(SESSION_FILE):
        logger.error("session.json file not found.")
        return {"success": False, "message": "LinkedIn session credentials not found on server."}

    logger.info(f"Received scraping request for profile: {url}")
    
    async with async_playwright() as p:
        browser = None
        try:
            # Launch chromium browser in headless mode
            browser = await p.chromium.launch(
                headless=True,
                args=["--no-sandbox", "--disable-setuid-sandbox", "--disable-dev-shm-usage"]
            )
            
            # Setup context with saved cookies
            context = await browser.new_context(
                viewport={"width": 1280, "height": 800},
                user_agent="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
            )
            
            # Load cookies
            with open(SESSION_FILE, "r") as f:
                cookies = json.load(f)
                if cookies:
                    await context.add_cookies(cookies)
                else:
                    return {"success": False, "message": "LinkedIn session.json is empty."}
                    
            page = await context.new_page()
            
            # Set default timeout to 15 seconds
            page.set_default_timeout(15000)
            
            # Navigate to profile URL
            logger.info("Navigating to LinkedIn profile...")
            response = await page.goto(url, wait_until="domcontentloaded")
            
            # Check for rate limit or redirection to login page
            current_url = page.url
            if "login" in current_url or "checkpoint" in current_url:
                logger.error(f"Session expired, redirected to: {current_url}")
                return {"success": False, "message": "LinkedIn session expired."}
                
            if response and response.status == 429:
                logger.error("Rate limit hit (429 Too Many Requests)")
                return {"success": False, "message": "LinkedIn rate limit exceeded."}
                
            if response and response.status == 404:
                logger.error("Profile not found (404)")
                return {"success": False, "message": "LinkedIn profile not found."}
                
            # Wait for name or main scaffold element
            try:
                await page.wait_for_selector("h1.text-heading-xlarge, main", timeout=8000)
            except Exception:
                logger.warning("Scaffold element wait timed out, attempting to scrape available DOM...")
                
            # Check if profile is unavailable or security wall is active
            title = await page.title()
            if "Security Header" in title or "Page Not Found" in title or "Quick security check" in page.content().__str__():
                logger.error(f"LinkedIn verification block triggered: {title}")
                return {"success": False, "message": "LinkedIn verification block triggered."}

            # Parse profile details
            name = ""
            name_el = await page.query_selector("h1.text-heading-xlarge, main section h1, h1")
            if name_el:
                name = (await name_el.inner_text()).strip()
                
            headline = ""
            headline_el = await page.query_selector("div.text-body-medium, [class*='text-body-medium']")
            if headline_el:
                headline = (await headline_el.inner_text()).strip()
                
            location = ""
            location_el = await page.query_selector("span.text-body-small.inline.t-16.t-black--light, [class*='text-body-smallinline']")
            if location_el:
                location = (await location_el.inner_text()).strip()
                
            about = ""
            about_el = await page.query_selector("#about ~ div.display-flex span[aria-hidden='true']")
            if about_el:
                about = (await about_el.inner_text()).strip()
                
            # Scrape experience list
            company = ""
            designation = ""
            
            experience_items = await page.query_query_all("[componentkey^='entity-collection-item']") if hasattr(page, 'query_query_all') else await page.query_selector_all("[componentkey^='entity-collection-item']")
            if not experience_items:
                # Fallback Experience Card list selection
                experience_items = await page.query_selector_all("div#experience ~ div.pvs-list__outer-container > ul > li, #experience-section li")
                
            if experience_items:
                first_exp = experience_items[0]
                
                # Check for nested multi-position structure (multiple roles at same company)
                nested_roles = await first_exp.query_selector_all("li")
                if len(nested_roles) > 0:
                    # In nested, the main item header contains the company name
                    comp_name_el = await first_exp.query_selector("span[aria-hidden='true']")
                    if comp_name_el:
                        company = (await comp_name_el.inner_text()).split("·")[0].strip()
                    
                    # The first nested item has the designation
                    role_title_el = await nested_roles[0].query_selector("span[aria-hidden='true']")
                    if role_title_el:
                        designation = (await role_title_el.inner_text()).strip()
                else:
                    # Single position structure
                    # Title
                    title_el = await first_exp.query_selector("span[aria-hidden='true'], [class*='title']")
                    if title_el:
                        designation = (await title_el.inner_text()).strip()
                    
                    # Company Line
                    comp_el = await first_exp.query_selector("span.t-14.t-normal, span.text-body-small, p")
                    if comp_el:
                        comp_text = (await comp_el.inner_text()).strip()
                        if " · " in comp_text or "·" in comp_text:
                            company = comp_text.split("·")[0].split(" \u00B7 ")[0].strip()
                        else:
                            company = comp_text

            # If company extraction was unsuccessful, try parsing headline
            if not company:
                if " at " in headline:
                    company = headline.split(" at ")[1].split("·")[0].strip()
                elif " @ " in headline:
                    company = headline.split(" @ ")[1].split("·")[0].strip()
                else:
                    company = "LinkedIn Member"
                    
            if not designation:
                designation = headline if headline else "Professional"
                
            logger.info(f"Successfully scraped {name}. Company: {company}, Designation: {designation}")
            
            return {
                "success": True,
                "name": name if name else "LinkedIn Member",
                "headline": headline,
                "company": company,
                "designation": designation,
                "location": location,
                "linkedin": url
            }
            
        except asyncio.TimeoutError:
            logger.error("Request timed out during profile loading.")
            return {"success": False, "message": "Scraping request timed out."}
        except Exception as e:
            logger.exception("Unexpected exception in scraper microservice")
            return {"success": False, "message": f"Unexpected scraper exception: {str(e)}"}
        finally:
            if browser:
                await browser.close()

if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="127.0.0.1", port=8000, reload=False)

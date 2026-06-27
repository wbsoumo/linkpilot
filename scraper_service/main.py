import os
import json
import logging
import re
import requests
from typing import Dict, Any
from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel
from bs4 import BeautifulSoup

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
    pattern = r"^https:\/\/(www\.)?linkedin\.com\/in\/[a-zA-Z0-9\-_%]+\/?$"
    return bool(re.match(pattern, url.split('?')[0]))

@app.middleware("http")
async def restrict_access(request: Request, call_next):
    client_host = request.client.host if request.client else ""
    api_key_header = request.headers.get("X-API-Key")
    
    if client_host not in ("127.0.0.1", "localhost") and api_key_header != API_KEY:
        logger.warning(f"Unauthorized access attempt from host {client_host}")
        return JSONResponse(status_code=401, content={"success": False, "message": "Unauthorized access."})
    
    return await call_next(request)

@app.post("/scrape")
async def scrape_profile(req: ScrapeRequest) -> Dict[str, Any]:
    url = req.linkedin_url.strip()
    url = url.split('?')[0]
    if not url.endswith('/'):
        url += '/'
        
    if not validate_linkedin_url(url):
        logger.error(f"Invalid LinkedIn URL: {url}")
        return {"success": False, "message": "Invalid LinkedIn Profile URL."}
        
    if not os.path.exists(SESSION_FILE):
        logger.error("session.json file not found.")
        return {"success": False, "message": "LinkedIn session credentials not found on server."}

    logger.info(f"Received HTTP-based scraping request for profile: {url}")
    
    try:
        # Load session cookies
        with open(SESSION_FILE, "r") as f:
            cookies = json.load(f)
            
        if not cookies:
            return {"success": False, "message": "LinkedIn session.json is empty."}
            
        # Map cookies list to dict format for requests
        cookie_dict = {c['name']: c['value'] for c in cookies}
        
        headers = {
            "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
            "Accept-Language": "en-US,en;q=0.9",
            "Connection": "keep-alive"
        }
        
        # 1. Fetch profile HTML
        res = requests.get(url, headers=headers, cookies=cookie_dict, timeout=15)
        
        if "login" in res.url or "checkpoint" in res.url:
            logger.error(f"Session expired. Redirected to: {res.url}")
            return {"success": False, "message": "LinkedIn session expired."}
            
        if res.status_code == 429:
            logger.error("Rate limit hit (429)")
            return {"success": False, "message": "LinkedIn rate limit exceeded."}
            
        if res.status_code == 404:
            logger.error("Profile not found (404)")
            return {"success": False, "message": "LinkedIn profile not found."}
            
        html = res.text
        
        # Check if security challenge is served
        if "Quick security check" in html or "Security Header" in html:
            logger.error("Security verification check wall triggered on LinkedIn.")
            return {"success": False, "message": "LinkedIn verification block triggered."}

        # 2. Extract fields using regex & BeautifulSoup
        resolved_company = ''
        company_urn = ''
        
        company_name_match = re.search(r'"companyName"\s*:\s*"([^"]+)"', html)
        if company_name_match:
            resolved_company = company_name_match.group(1).replace('\\"', '"').strip()
            
        company_urn_match = re.search(r'urn:li:fsd_company:(\d+)', html)
        if company_urn_match and company_urn_match.group(1) != '96420083':
            company_urn = company_urn_match.group(1)
            
        if not company_urn:
            matches = re.findall(r'\\?/company\\?/([a-zA-Z0-9\-_]+)', html)
            for u in matches:
                u_lower = u.lower()
                if u_lower not in ('linkedin', '96420083', 'invalid', 'setup'):
                    company_urn = u
                    break

        name = 'LinkedIn Member'
        soup = BeautifulSoup(html, 'html.parser')
        
        # Try title header parsing first
        title_text = soup.title.string if soup.title else ""
        if title_text and "|" in title_text:
            name_candidate = title_text.split("|")[0].strip()
            if name_candidate and name_candidate.lower() != "linkedin":
                name = name_candidate
                
        if name == 'LinkedIn Member':
            name_match = re.search(r'"firstName":"([^"]+)".*?"lastName":"([^"]+)"', html)
            if name_match:
                name = f"{name_match.group(1)} {name_match.group(2)}".strip()
                
        headline = ""
        headline_match = re.search(r'"headline":"([^"]+)"', html)
        if headline_match:
            headline = headline_match.group(1).replace('\\"', '"').strip()
            
        location = ""
        location_match = re.search(r'"locationName":"([^"]+)"', html)
        if location_match:
            location = location_match.group(1).strip()

        # Fallback for company name
        if not resolved_company:
            if headline and " at " in headline:
                resolved_company = headline.split(" at ")[1].split("·")[0].strip()
            elif headline and " @ " in headline:
                resolved_company = headline.split(" @ ")[1].split("·")[0].strip()
            else:
                resolved_company = "LinkedIn Member"
                
        logger.info(f"Successfully scraped profile: {name}. Company: {resolved_company}")
        
        return {
            "success": True,
            "name": name,
            "headline": headline,
            "company": resolved_company,
            "designation": headline if headline else "Professional",
            "location": location,
            "linkedin": url
        }
        
    except requests.Timeout:
        logger.error("Profile scrape request timed out.")
        return {"success": False, "message": "Scraping request timed out."}
    except Exception as e:
        logger.exception("Unexpected error inside HTTP profile scraper")
        return {"success": False, "message": f"Unexpected scraper exception: {str(e)}"}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="127.0.0.1", port=8000, reload=False)

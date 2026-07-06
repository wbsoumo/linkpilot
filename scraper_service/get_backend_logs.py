import pymysql
import json

# Fetch database configuration from config.php dynamically
# Or just connect directly using standard credentials
try:
    connection = pymysql.connect(
        host="localhost",
        user="root",
        password="",
        database="linkpilot",
        cursorclass=pymysql.cursors.DictCursor
    )
except Exception as e:
    # Try alternate credentials if any
    try:
        # Check config.php for credentials
        with open("backend/config.php", "r") as f:
            content = f.read()
        db_user = re.search(r"'DB_USER',\s*'([^']+)'", content).group(1)
        db_pass = re.search(r"'DB_PASS',\s*'([^']*)'", content).group(1)
        db_name = re.search(r"'DB_NAME',\s*'([^']+)'", content).group(1)
        connection = pymysql.connect(
            host="localhost",
            user=db_user,
            password=db_pass,
            database=db_name,
            cursorclass=pymysql.cursors.DictCursor
        )
    except Exception as ex:
        print("Failed to connect to database:", ex)
        exit(1)

try:
    with connection.cursor() as cursor:
        print("--- scraper_requests_log ---")
        cursor.execute("SELECT * FROM scraper_requests_log ORDER BY id DESC LIMIT 5")
        logs = cursor.fetchall()
        print(json.dumps(logs, indent=2))
        
        print("\n--- email_search_history ---")
        cursor.execute("SELECT * FROM email_search_history ORDER BY id DESC LIMIT 5")
        history = cursor.fetchall()
        print(json.dumps(history, indent=2))
        
        print("\n--- email_provider_settings ---")
        cursor.execute("SELECT * FROM email_provider_settings")
        providers = cursor.fetchall()
        print(json.dumps(providers, indent=2))
finally:
    connection.close()

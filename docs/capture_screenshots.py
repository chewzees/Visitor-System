"""Capture screenshots of Visitor System pages via normal login."""
from __future__ import annotations

import re
import time
from pathlib import Path

from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

BASE = "http://localhost/Visitor"
OUT = Path(__file__).resolve().parent / "screenshots"
OUT.mkdir(parents=True, exist_ok=True)

PUBLIC = [
    ("01-login", f"{BASE}/login.php"),
    ("02-forgot-password", f"{BASE}/forgot.php"),
    ("03-manual", f"{BASE}/manual.php"),
    ("04-checkin", f"{BASE}/checkin.php"),
    ("05-checkin-form", f"{BASE}/checkin.php?e=entrance"),
]


def make_driver() -> webdriver.Chrome:
    opts = Options()
    opts.add_argument("--headless=new")
    opts.add_argument("--disable-gpu")
    opts.add_argument("--window-size=1440,900")
    opts.add_argument("--hide-scrollbars")
    opts.add_argument("--force-device-scale-factor=1")
    return webdriver.Chrome(options=opts)


def shot(driver: webdriver.Chrome, name: str) -> None:
    path = OUT / f"{name}.png"
    time.sleep(0.6)
    driver.save_screenshot(str(path))
    print(f"OK {name} ({path.stat().st_size} bytes)")


def main() -> None:
    driver = make_driver()
    wait = WebDriverWait(driver, 15)
    try:
        for name, url in PUBLIC:
            driver.get(url)
            shot(driver, name)

        # Normal login
        driver.get(f"{BASE}/login.php")
        wait.until(EC.presence_of_element_located((By.ID, "username")))
        user = driver.find_element(By.ID, "username")
        pwd = driver.find_element(By.ID, "password")
        user.clear()
        user.send_keys("admin")
        pwd.clear()
        pwd.send_keys("password")
        driver.find_element(By.CSS_SELECTOR, "button.btn-auth[type='submit']").click()
        wait.until(EC.url_contains("dashboard.php"))
        shot(driver, "06-dashboard")

        # visitor id for detail/badge
        driver.get(f"{BASE}/visitors.php")
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "table.data")))
        shot(driver, "07-visitors")
        html = driver.page_source
        m = re.search(r"visitor_view\.php\?id=(\d+)", html)
        vid = m.group(1) if m else "1"

        auth_pages = [
            ("08-visitor-form", f"{BASE}/visitor_form.php"),
            ("09-visitor-view", f"{BASE}/visitor_view.php?id={vid}"),
            ("10-badge", f"{BASE}/badge.php?id={vid}"),
            ("11-scan", f"{BASE}/scan.php"),
            ("12-entrance-qr", f"{BASE}/entrance.php"),
            ("13-blacklist", f"{BASE}/blacklist.php"),
            ("14-activity-log", f"{BASE}/activity.php"),
            ("15-users", f"{BASE}/users.php"),
            ("16-settings", f"{BASE}/settings.php"),
        ]
        for name, url in auth_pages:
            driver.get(url)
            wait.until(EC.presence_of_element_located((By.TAG_NAME, "body")))
            shot(driver, name)

        print("DONE")
    finally:
        driver.quit()


if __name__ == "__main__":
    main()

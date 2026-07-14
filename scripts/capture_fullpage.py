from pathlib import Path
from playwright.sync_api import sync_playwright
import http.server
import socketserver
import threading

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "preview-pagina-completa.png"
PORT = 8765


class QuietHandler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def log_message(self, format, *args):
        pass


httpd = socketserver.TCPServer(("127.0.0.1", PORT), QuietHandler)
httpd.allow_reuse_address = True
thread = threading.Thread(target=httpd.serve_forever, daemon=True)
thread.start()

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page(viewport={"width": 1440, "height": 900}, device_scale_factor=1)
    page.goto(f"http://127.0.0.1:{PORT}/index.html", wait_until="networkidle", timeout=60000)
    page.wait_for_timeout(2500)
    page.evaluate(
        """() => {
          const splash = document.getElementById('splash');
          if (splash) splash.remove();
          document.body.classList.remove('is-splashing');
          document.body.classList.add('is-loaded');
          const cookie = document.getElementById('cookieBanner');
          if (cookie) cookie.classList.remove('is-visible');
          try { localStorage.setItem('jota_cookie_consent', 'rejected'); } catch (e) {}
        }"""
    )
    page.wait_for_timeout(800)
    page.screenshot(path=str(OUT), full_page=True)
    browser.close()

httpd.shutdown()
print(OUT)
print("bytes", OUT.stat().st_size)

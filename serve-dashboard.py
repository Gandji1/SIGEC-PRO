#!/usr/bin/env python3
"""
Simple HTTP server to serve SIGEC dashboard
"""
import http.server
import socketserver
import os
import sys

PORT = 8888
DIRECTORY = "/workspaces/SIGEC"

class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=DIRECTORY, **kwargs)
    
    def log_message(self, format, *args):
        """Custom log format"""
        sys.stderr.write("[%s] %s\n" % (self.log_date_time_string(), format % args))

if __name__ == "__main__":
    os.chdir(DIRECTORY)
    
    with socketserver.TCPServer(("", PORT), Handler) as httpd:
        print(f"""
╔════════════════════════════════════════════════════════════════╗
║          🚀 SIGEC Dashboard Server Started                    ║
╚════════════════════════════════════════════════════════════════╝

📱 Open in browser:
   http://localhost:{PORT}/dashboard.html

📊 Show project status:
   http://localhost:{PORT}/status.sh (text view)

Press CTRL+C to stop
""")
        httpd.serve_forever()

#!/bin/bash
# chmod +x to the script and run it as a superuser
cp xsolla_server.pem /etc/ssl/certs/
cp xsolla_server.key /etc/ssl/private/
chown root:root /etc/ssl/certs/xsolla_server.pem
chown root:ssl-cert /etc/ssl/private/xsolla_server.key
chmod 644 /etc/ssl/certs/xsolla_server.pem
chmod 640 /etc/ssl/private/xsolla_server.key

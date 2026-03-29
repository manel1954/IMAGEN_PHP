sudo tee /home/pi/A108/instalar_displaydriver.sh << 'EOF'
#!/bin/bash
if [ -d "/home/pi/Display-Driver" ]; then
    echo "El Display-Driver ya ha sido instalado."
    exit 0
fi

cd /home/pi
git clone https://github.com/g4klx/Display-Driver.git
sleep 2
cd Display-Driver
make
make install
chmod 777 -R /home/pi/Display-Driver
apt-get install -y php-zip
systemctl restart apache2

tee /etc/systemd/system/displaydriver.service << 'SVCEOF'
[Unit]
Description=MMDVM Display-Driver for Nextion via MQTT
After=mosquitto.service mmdvmhost.service
[Service]
Type=simple
User=root
WorkingDirectory=/home/pi/Display-Driver
ExecStart=/home/pi/Display-Driver/DisplayDriver DisplayDriver.ini
Restart=always
[Install]
WantedBy=multi-user.target
SVCEOF

systemctl daemon-reload
systemctl enable displaydriver.service
systemctl start displaydriver.service
echo "Instalacion completada correctamente."
EOF
sudo chmod +x /home/pi/A108/instalar_displaydriver.sh
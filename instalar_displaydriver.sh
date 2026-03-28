#!/bin/bash
cd /home/pi
git clone https://github.com/g4klx/Display-Driver.git
echo "Instalando Display Driver para Nextion"
read a -n 1 -s -r -p "Presione cualquier tecla para continuar con la instalación del Display Driver para Nextion"
cd Display-Driver
make
sudo make install

sudo chmod 777 -R ~/Display-Driver


sudo apt-get install -y php-zip
sudo systemctl restart apache2
#sudo nano /etc/php/8.2/apache2/php.ini

sudo tee /etc/systemd/system/displaydriver.service << 'EOF'
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
EOF

sudo systemctl daemon-reload
sudo systemctl enable displaydriver.service
sudo systemctl start displaydriver.service

#grep -q "www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart displaydriver.service" /etc/sudoers || echo "www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart displaydriver.service" | sudo EDITOR='tee -a' visudo


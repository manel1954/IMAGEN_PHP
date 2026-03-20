#!/bin/bash

                        #14-08-2020 cambio actualizar para que salgan los indicativos en DVSWITCH:
                        cd /var/lib/mmdvm
                        sudo curl --fail -o DMRIds.dat -s http://www.pistar.uk/downloads/DMRIds.dat
                        sudo chmod 777 -R /var/lib/mmdvm
                        cp DMRIds.dat /home/pi/DMR2YSF/
                        cp DMRIds.dat /home/pi/YSF2DMR/
                        sleep 5
                        
                        # Cambio realizado el 14-04-2025 para actualizar los IDS en MMDVMHost 
                        cd /home/pi/MMDVMHost
                        sudo curl --fail -o DMRIds.dat -s http://www.pistar.uk/downloads/DMRIds.dat
                        #cp DMRIds.dat /home/pi/MMDVMHost/
                        sudo chmod 777 /home/pi/MMDVMHost/DMRIds.dat

                        

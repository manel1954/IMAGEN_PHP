#!/bin/bash
#cd /home/pi/IMAGEN_PHP
#git fetch --all
#sleep 5
#git reset --hard origin/main
#echo "Imagen actualizada a la última versión disponible en el repositorio de GitHub"
#sleep 5
#sudo chmod 777 -R /home/pi/IMAGEN_PHP

                        cd /home/pi/IMAGEN_PHP                                             
                        git pull --force                      
                        sudo rm -R /home/pi/A108
                        mkdir /home/pi/A108                                                
                        cp -R /home/pi/IMAGEN_PHP/* /home/pi/A108
                        sleep 6                                             
                        sudo chmod 777 -R /home/pi/A108                  

                        
                 


                        
                    

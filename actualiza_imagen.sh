#!/bin/bash
cd /home/pi/IMAGEN_PHP
git fetch --all
sleep 5
git reset --hard origin/main
echo "Imagen actualizada a la última versión disponible en el repositorio de GitHub"
sleep 5
sudo chmod 777 -R /home/pi/IMAGEN_PHP

                        

                        
                 


                        
                    

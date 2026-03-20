#!/bin/bash
#Colores
ROJO="\033[1;31m"
VERDE="\033[1;32m"
BLANCO="\033[1;37m"
AMARILLO="\033[1;33m"
CIAN="\033[1;36m"
GRIS="\033[0m"
MARRON="\33[38;5;138m"

			# ACTUALIZAR REFLECTORES YSF desde mi github
			#==============================================================
			#cp /home/pi/A108/YSFHosts.txt /home/pi/YSFClients/YSFGateway/
			#sleep 3			
			#clear
			#echo "${VERDE}***********************************************"
			#		echo "*  ACTUALIZANDO REFLECTORES YSF               *"
			#		echo "***********************************************"
			#sleep 3
			#==============================================================


			cd /home/pi/
            wget --user-agent="YSFGateway" https://hostfiles.refcheck.radio/YSFHosts.txt
            sudo mv /home/pi/YSFHosts.txt /home/pi/YSFClients/YSFGateway/
			sleep 3	
# Image WordPress locale du projet MASSIFS.
#
# Ajoute uniquement ce qu'il faut pour que /tiles/ soit servi en même origine
# que le site (contrainte §3 du brief : le navigateur ne doit jamais contacter
# un domaine tiers pour le fond de carte), que les réponses texte/JSON soient
# compressées (budget §10 du brief), et qu'aucun fichier .php de thème ou
# d'extension ne soit atteignable par requête HTTP directe. Aucun code
# applicatif ici : le thème et l'extension restent montés depuis le dépôt
# (voir docker-compose.yml).
FROM wordpress:php8.3-apache

RUN a2enmod proxy proxy_http deflate

COPY tiles-proxy.conf /etc/apache2/conf-enabled/tiles-proxy.conf
COPY deflate.conf /etc/apache2/conf-enabled/massifs-deflate.conf
COPY plugins-guard.conf /etc/apache2/conf-enabled/massifs-plugins-guard.conf

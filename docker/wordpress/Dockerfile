# Image WordPress locale du projet MASSIFS.
#
# Ajoute uniquement ce qu'il faut pour que /tiles/ soit servi en même origine
# que le site (contrainte §3 du brief : le navigateur ne doit jamais contacter
# un domaine tiers pour le fond de carte). Aucun code applicatif ici : le thème
# et l'extension restent montés depuis le dépôt (voir docker-compose.yml).
FROM wordpress:php8.3-apache

RUN a2enmod proxy proxy_http

COPY tiles-proxy.conf /etc/apache2/conf-enabled/tiles-proxy.conf

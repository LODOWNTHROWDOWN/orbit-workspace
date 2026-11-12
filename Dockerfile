FROM node:22-alpine AS assets
WORKDIR /build
COPY package*.json ./
RUN npm install --no-audit --no-fund
COPY index.html vite.config.js ./
COPY src ./src
RUN npm run build

FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=assets /build/dist/ /var/www/html/
COPY server/public/ /var/www/html/api/
COPY server/src/ /opt/orbit/src/
COPY server/bin/ /opt/orbit/bin/
COPY database/ /opt/orbit/database/
COPY ops/apache.conf /etc/apache2/sites-available/000-default.conf
COPY ops/start.sh /usr/local/bin/orbit-start
RUN chmod +x /usr/local/bin/orbit-start
ENV PORT=8080
CMD ["orbit-start"]

FROM node:24-bookworm-slim AS frontend

WORKDIR /app
COPY apps/frontend/package.json apps/frontend/package-lock.json ./
RUN npm ci
COPY apps/frontend ./
RUN npm run build

FROM nginx:1.27-alpine

COPY infrastructure/nginx/production.conf /etc/nginx/conf.d/default.conf
COPY infrastructure/nginx/oneflo-app.conf /etc/nginx/snippets/oneflo-app.conf
COPY infrastructure/nginx/custom-domain.conf.template /etc/nginx/templates/custom-domain.conf.template
COPY --from=frontend /app/dist /usr/share/nginx/html

RUN mkdir -p /var/www/certbot /etc/nginx/conf.d/custom

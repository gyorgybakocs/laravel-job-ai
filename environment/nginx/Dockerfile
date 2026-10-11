ARG NGINX_DOCKER_IMAGE
FROM ${NGINX_DOCKER_IMAGE}

ARG ENV
ARG APP_DOMAIN

RUN rm -f /etc/nginx/conf.d/default.conf
COPY nginx/conf/includes/ /etc/nginx/includes/
COPY nginx/conf/nginx.conf /etc/nginx/nginx.conf
COPY nginx/conf/${ENV}.conf /tmp/nginx.conf
RUN envsubst '${APP_DOMAIN}' < /tmp/nginx.conf > /etc/nginx/conf.d/${APP_DOMAIN}.conf && \
    rm /tmp/nginx.conf

EXPOSE 80 443

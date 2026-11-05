# syntax=docker/dockerfile:1.7
#
# Stage 1: build aset statis dengan Node.  Stage 2: sajikan dengan Nginx non-root (uid 101).

########## Stage 1: build ##########
FROM node:22-alpine AS build
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
# URL API dibaca Vite saat build (harus bisa diakses dari BROWSER, bukan dari container).
ARG VITE_API_URL=http://localhost:8000/api
ENV VITE_API_URL=${VITE_API_URL}
RUN npm run build

########## Stage 2: runtime ##########
FROM nginxinc/nginx-unprivileged:1.27-alpine AS runtime
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
USER nginx
EXPOSE 8080
HEALTHCHECK --interval=15s --timeout=3s CMD wget -qO- http://127.0.0.1:8080/ >/dev/null || exit 1

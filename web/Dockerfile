# Front Cap Monta : Angular rendu côté serveur (Node), les 4 langues, et le relais de /api
# et /media vers l'API (server.ts).

FROM node:24-slim AS build
WORKDIR /app
COPY package.json package-lock.json ./
# Les scripts d'installation (sharp, esbuild…) ne servent qu'aux outils de build locaux.
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY . .
RUN npm run build

# Le serveur est un seul fichier qui embarque ses dépendances : pas de node_modules ici.
FROM node:24-slim
WORKDIR /app
ENV NODE_ENV=production
COPY --from=build /app/dist ./dist
USER node
CMD ["node", "dist/web/server/server.mjs"]

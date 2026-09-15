'use strict';

const http = require('http');
const { createApp } = require('./src/app');

const PORT = Number(process.env.PORT || 8787);
const HOST = process.env.HOST || '0.0.0.0';

const server = http.createServer(createApp());
server.listen(PORT, HOST, () => {
  console.log(`GTA VISOTE  http://${HOST}:${PORT}  (Node, no PHP)`);
});

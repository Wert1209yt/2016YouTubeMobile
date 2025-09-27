// server.js
const http = require("http");
const fs = require("fs");
const path = require("path");

const PORT = process.env.PORT || 3000;

const assetsPath = path.join(__dirname, "assets");
const feedFilePath = path.join(__dirname, "API", "feed.txt");

const server = http.createServer((req, res) => {
  // Игнорируем query-параметры (оставляем только путь)
  const url = req.url.split("?")[0];

  if (url === "/feed") {
    // Читаем файл feed.txt и отдаём
    fs.readFile(feedFilePath, "utf8", (err, data) => {
      if (err) {
        res.writeHead(500, { "Content-Type": "text/plain; charset=utf-8" });
        return res.end("Ошибка чтения API/feed.txt");
      }
      res.writeHead(200, { "Content-Type": "text/plain; charset=utf-8" });
      res.end(data);
    });
  } else {
    // Раздаём index.html и файлы из папки assets
    let filePath = url === "/" ? "/index.html" : url;
    const fullPath = path.join(assetsPath, filePath);

    fs.readFile(fullPath, (err, data) => {
      if (err) {
        res.writeHead(404, { "Content-Type": "text/plain; charset=utf-8" });
        return res.end("Файл не найден");
      }

      // Определяем content-type по расширению
      const ext = path.extname(fullPath).toLowerCase();
      const contentType =
        ext === ".html"
          ? "text/html"
          : ext === ".css"
          ? "text/css"
          : ext === ".js"
          ? "application/javascript"
          : "application/octet-stream";

      res.writeHead(200, { "Content-Type": contentType + "; charset=utf-8" });
      res.end(data);
    });
  }
});

server.listen(PORT, () => {
  console.log(`Сервер запущен: http://localhost:${PORT}`);
});
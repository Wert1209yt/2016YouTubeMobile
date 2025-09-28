// server.js
const http = require("http");
const fs = require("fs");
const path = require("path");
const { spawn } = require("child_process");

const PORT = process.env.PORT || 3000;

const assetsPath = path.join(__dirname, "assets");
const feedFilePath = path.join(__dirname, "API", "feed.json);
const searchPhpPath = path.join(__dirname, "API", "search.php");

let lastSearchQuery = "";

// ---- Функция запуска PHP с нужными переменными ----
function runPHP(filePath, query, callback) {
  const php = spawn("php-cgi", [], {
    env: {
      ...process.env,
      REQUEST_METHOD: "GET",
      SCRIPT_FILENAME: filePath,
      QUERY_STRING: query,
      REDIRECT_STATUS: 200 // ✅ ключевой момент для обхода security alert
    }
  });

  let output = "";
  php.stdout.on("data", (chunk) => (output += chunk));
  php.stderr.on("data", (err) => console.error("PHP error:", err.toString()));
  php.on("close", () => callback(output));
}

const server = http.createServer((req, res) => {
  const [urlPath, queryString] = req.url.split("?");
  const queryParams = new URLSearchParams(queryString || "");

  if (urlPath === "/feed") {
    fs.readFile(feedFilePath, "utf8", (err, data) => {
      if (err) {
        res.writeHead(500, { "Content-Type": "text/plain; charset=utf-8" });
        return res.end("Ошибка чтения API/feed.txt");
      }
      res.writeHead(200, { "Content-Type": "text/plain; charset=utf-8" });
      res.end(data);
    });

  } else if (urlPath === "/results") {
    const q = queryParams.get("q") || "";
    lastSearchQuery = q;

    const query = `q=${encodeURIComponent(q)}`;
    runPHP(searchPhpPath, query, (phpOutput) => {
      const body = phpOutput.replace(/^.*?\r\n\r\n/s, ""); // убираем CGI-заголовки
      res.writeHead(200, { "Content-Type": "text/html; charset=utf-8" });
      res.end(body);
    });

  } else if (urlPath === "/searchquery") {
    res.writeHead(200, { "Content-Type": "text/plain; charset=utf-8" });
    res.end(lastSearchQuery);

  } else {
    const filePath = urlPath === "/" ? "/index.html" : urlPath;
    const fullPath = path.join(assetsPath, filePath);

    fs.readFile(fullPath, (err, data) => {
      if (err) {
        res.writeHead(404, { "Content-Type": "text/plain; charset=utf-8" });
        return res.end("Файл не найден");
      }

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
  console.log(`Server started: http://localhost:${PORT}`);
});

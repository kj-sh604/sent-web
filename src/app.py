#!/usr/bin/env python3

import base64
import os
import re
import secrets
import subprocess
from pathlib import Path

import magic
from flask import Flask, Response, jsonify, request, send_file, send_from_directory

app = Flask(__name__, static_folder=None)
app.config["MAX_CONTENT_LENGTH"] = 50 * 1024 * 1024  # 50 MB upload cap

UPLOAD_DIR = Path(__file__).parent / "uploads"
UPLOAD_DIR.mkdir(mode=0o755, exist_ok=True)

ALLOWED_MIME = {
    "image/png":     "png",
    "image/jpeg":    "jpg",
    "image/gif":     "gif",
    "image/webp":    "webp",
    "image/svg+xml": "svg",
    "image/bmp":     "bmp",
}

# build list of allowed font directories
_FONT_DIRS: list[Path] = [
    Path("/usr/share/fonts"),
    Path("/usr/local/share/fonts"),
]
for _home in Path("/home").glob("*"):
    _FONT_DIRS.append(_home / ".local" / "share" / "fonts")
    _FONT_DIRS.append(_home / ".fonts")


def _allowed_font_dirs() -> list[str]:
    """return resolved, existent font dirs with trailing separator."""
    out = []
    for d in _FONT_DIRS:
        try:
            out.append(str(d.resolve(strict=True)) + os.sep)
        except (OSError, RuntimeError):
            pass
    return out


@app.route("/")
def index():
    return send_from_directory(app.root_path, "index.html")


@app.route("/uploads/<filename>")
def uploads(filename: str):
    return send_from_directory(UPLOAD_DIR, filename)


@app.route("/upload", methods=["POST"])
def upload():
    if "image" not in request.files:
        return jsonify({"error": "no file provided"}), 400

    f = request.files["image"]
    if not f.filename:
        return jsonify({"error": "empty filename"}), 400

    data = f.read()
    if not data:
        return jsonify({"error": "empty file"}), 400

    mime = magic.from_buffer(data, mime=True)
    if mime not in ALLOWED_MIME:
        return jsonify({"error": f"invalid file type: {mime}"}), 400

    ext = ALLOWED_MIME[mime]
    basename = re.sub(r"[^a-zA-Z0-9_-]", "_", Path(f.filename).stem)[:64]
    filename = f"{basename}_{secrets.token_hex(4)}.{ext}"

    (UPLOAD_DIR / filename).write_bytes(data)

    return jsonify({"filename": filename, "url": f"uploads/{filename}"})


@app.route("/fonts")
def fonts():
    try:
        result = subprocess.run(
            ["fc-list", "--format=%{family}|%{style}|%{file}\n"],
            capture_output=True,
            text=True,
            shell=False,
            timeout=10,
            check=False,
        )
    except (FileNotFoundError, subprocess.TimeoutExpired):
        return jsonify([])

    if result.returncode != 0:
        return jsonify([])

    if not result.stdout.strip():
        return jsonify([])

    def style_score(style: str) -> int:
        s = style.strip().lower()
        if s in ("regular", "roman", "book", "text"):
            return 0
        if s == "bold":
            return 1
        if "italic" in s or "oblique" in s:
            return 2
        return 3

    best: dict[str, dict] = {}
    for line in result.stdout.splitlines():
        parts = line.split("|", 2)
        if len(parts) < 3:
            continue
        family = parts[0].split(",")[0].strip()
        if not family:
            continue
        style     = parts[1].split(",")[0].strip()
        file_path = parts[2].strip()
        if not Path(file_path).exists():
            continue
        score = style_score(style)
        if family not in best or score < best[family]["score"]:
            best[family] = {"file": file_path, "score": score}

    fmt_map = {"ttf": "truetype", "otf": "opentype", "woff": "woff", "woff2": "woff2"}
    fonts_list = [
        {
            "family": family,
            "file":   base64.b64encode(entry["file"].encode()).decode(),
            "format": fmt_map.get(Path(entry["file"]).suffix.lstrip(".").lower(), "truetype"),
        }
        for family, entry in best.items()
    ]
    fonts_list.sort(key=lambda x: x["family"].casefold())

    resp = jsonify(fonts_list)
    resp.headers["Cache-Control"] = "public, max-age=3600"
    return resp


@app.route("/font")
def font():
    encoded = request.args.get("f", "")
    if not encoded:
        return Response("missing parameter", status=400)

    try:
        file_str = base64.b64decode(encoded).decode("utf-8")
    except Exception:
        return Response("invalid parameter", status=400)

    # null byte guard
    if "\x00" in file_str:
        return Response("invalid parameter", status=400)

    p = Path(file_str)
    if not p.exists():
        return Response("font not found", status=404)

    try:
        real = p.resolve(strict=True)
    except (OSError, RuntimeError):
        return Response("font not found", status=404)

    # path traversal guard: real path must be under an allowed font dir
    real_str = str(real) + os.sep
    if not any(real_str.startswith(d) for d in _allowed_font_dirs()):
        return Response("access denied", status=403)

    mime_map = {
        "ttf":   "font/ttf",
        "otf":   "font/otf",
        "woff":  "font/woff",
        "woff2": "font/woff2",
    }
    mime = mime_map.get(real.suffix.lstrip(".").lower(), "application/octet-stream")

    return send_file(real, mimetype=mime, max_age=31536000, conditional=True)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=3000)

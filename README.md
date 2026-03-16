# sent-web

> suckless's sent tool ported to the very sucky web world

A web-based reimplementation of [suckless sent](https://tools.suckless.org/sent/)
using Python and vanilla JavaScript.

<img width="1280" height="800" alt="sent0" src="https://github.com/user-attachments/assets/0c503bd4-3609-4e36-ae23-77b7f0711736" />
<br><br>
<img width="1920" height="1080" alt="sent1" src="https://github.com/user-attachments/assets/00a6880b-e310-45ce-8de2-6733e8d2676f" />

## features

- **sent-compatible format** - paragraphs = slides, `#` comments, `@image`
  slides, `\` escapes
- **keyboard navigation** - arrow keys, hjkl, space, enter, backspace, pgup/pgdn
  (same as sent)
- **mouse navigation** - left-click right half = next, left half = prev, scroll
  wheel
- **image upload** - upload images and insert `@filename` references (50 MB cap)
- **export** - download as `.sent` file for local sent, or export `.pdf` for portability
## usage

### docker compose (recommended)

```sh
docker compose up -d
```

Open [http://localhost:3000](http://localhost:3000).

### docker build

```sh
docker build -t sent-web .
docker run -d -p 3000:3000 --init --name sent-web sent-web
```

### local python run (without docker)

Requirements:

- Python `3.12+`
- `fontconfig` (`fc-list` must be available)
- `libmagic` runtime (`libmagic1` on Ubuntu)

Setup:

```sh
cd src
python3.12 -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
gunicorn --bind 0.0.0.0:3000 --workers 2 app:app
```

Then open [http://localhost:3000](http://localhost:3000).

### presentation shortcuts

| key                                          | action   |
|----------------------------------------------|----------|
| `F5`                                         | present  |
| `Escape` / `q`                               | exit     |
| `→` `↓` `Space` `Enter` `l` `j` `n` `PgDn`   | next     |
| `←` `↑` `Backspace` `h` `k` `p` `PgUp`       | previous |

### sent format

```
first slide title

second slide
with multiple lines

# this is a comment (ignored)

@image.png

\@this line starts with a literal @

\
```

## technology

- **Python 3.12+** - Flask backend
- **vanilla JavaScript** - no npm, no webpack, no react
- **[noir.css](https://github.com/kj-sh604/noir.css)** - classless CSS
- **Gunicorn** - production WSGI server
- **fontconfig** - `fc-list` for font enumeration
- **python-magic + libmagic** - content-based upload type checks
- **Docker** - containerized with fonts pre-installed

## license

MIT

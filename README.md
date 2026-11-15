# Athena AI for Infliction Gym

**Strategic Conversations, Automated.**

The Infliction Gym (Magalang, Pampanga) website with **Athena AI**, a chatbot that answers visitors 24/7 on the website and on the gym's Facebook page (Messenger). It checks class availability, books and cancels classes for members, and saves leads for the front desk.

Built on the project's free tool stack: **Laravel 12 + MySQL + Groq API** (with Hugging Face Inference as a backup), Ngrok for Messenger testing, Render/Railway/Supabase for hosting, GitHub and Postman.

## Features

| Feature | Where |
|---|---|
| Website inquiry widget (Athena AI chat) | Every page, bottom right |
| Automated Messenger support | `POST /api/webhook/facebook` |
| Lead capture and logging | Athena AI `save_lead` tool, Contact form, `/admin/leads` |
| Business knowledge base (FAQs, prices, hours) | `/admin/knowledge` |
| Class schedule and booking | `/schedule`, `/dashboard`, or ask Athena AI |
| Member accounts | `/register`, `/login`, `/dashboard` |
| Conversation log | `/admin/conversations` |

## Set up after cloning from GitHub

The `vendor` folder and `.env` are not in the repository, so do this once:

```
cd C:\xampp\htdocs
git clone https://github.com/DanielFranco0812/Athena-AI-chatbot.git Chatbot
cd Chatbot
composer install
copy .env.example .env
php artisan key:generate
```

Then open `.env` and paste your own `GROQ_API_KEY` (free at console.groq.com). Never commit `.env`.

## Run it in XAMPP

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open <http://localhost/Chatbot/public/>.
   With `AUTO_SETUP=true` in `.env`, the first visit creates the `athena_ai` database, runs the migrations and seeds the data.
3. Front desk login: `admin@infliction.ph` / `Admin@123` (change it after logging in).

To do the setup by hand instead, run this in the XAMPP Shell:

```
cd C:\xampp\htdocs\Chatbot
php artisan migrate --seed
php artisan serve        # http://127.0.0.1:8000
```

## Environment (.env)

| Key | Purpose |
|---|---|
| `DB_*` | MySQL connection (XAMPP: root, empty password) |
| `GROQ_API_KEY` | Free key from console.groq.com |
| `GROQ_MODELS` | Models tried in order. Llama left Groq's free tier in Aug 2026, so use `openai/gpt-oss-120b,openai/gpt-oss-20b` |
| `HF_TOKEN`, `HF_MODEL` | Optional backup through Hugging Face Inference |
| `META_VERIFY_TOKEN` | Any secret string. Enter the same one in the Meta app webhook settings |
| `META_PAGE_ACCESS_TOKEN` | Page token for the Infliction Facebook page |
| `META_APP_SECRET` | Lets Laravel check that webhook calls really come from Meta |
| `AUTO_SETUP` | `true` for local XAMPP only |

## Connect Facebook Messenger (testing with Ngrok)

1. `ngrok http 80` (or `ngrok http 8000` with `php artisan serve`).
2. In the Meta developer app, open **Messenger > Webhooks** and set:
   - Callback URL: `https://<ngrok-domain>/Chatbot/public/api/webhook/facebook` (with `artisan serve`: `https://<ngrok-domain>/api/webhook/facebook`)
   - Verify token: the value of `META_VERIFY_TOKEN`
   - Subscribe to `messages` and `messaging_postbacks`
3. Generate a Page access token for the Infliction page and put it in `META_PAGE_ACCESS_TOKEN`.

## Deploy for free

- **Render**: push to GitHub, then New > Blueprint (uses `render.yaml` and `Dockerfile`). Free web services sleep after 15 minutes without traffic, and free Render Postgres expires after 30 days.
- **Supabase**: for a database that doesn't expire, create a free Supabase project and set `DB_CONNECTION=pgsql` and `DB_URL` to its connection string.
- **Railway**: the Dockerfile also works there ($5 trial credit, then $1/month of free credit).

## Tests

```
php artisan test
```

There are 36 feature tests (117 assertions) covering pages, auth, booking rules, the Athena AI chat (with Groq faked), the Messenger webhook, the admin knowledge base and lead capture.

## Team

- James Mercado: AI/Chatbot developer
- Kian Sinio, Igor Toledano: Backend & integration
- Jehd Franco: QA, documentation

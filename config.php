<?php
// ИИ чат-боттың баптаулары (Anthropic Claude API).
// Кілтті https://console.anthropic.com бетінен алыңыз. Бос қалдырсаңыз, бот кілт сөздер бойынша жұмыс істей береді.
// Бұл файлды басқаларға жібермеңіз және GitHub-қа жүктемеңіз: кілт құпия.
if (!defined('AI_API_KEY')) define('AI_API_KEY', getenv('ANTHROPIC_API_KEY') ?: '');
if (!defined('AI_MODEL'))   define('AI_MODEL', 'claude-haiku-5-5');

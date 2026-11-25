<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f7f5f0">
  <meta name="description" content="A piano that learns from the notes people play.">
  <title>Piano</title>
  <link rel="icon" href="meta/piano-icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="meta/apple-touch-icon.png">
  <link href="style/main.css" rel="stylesheet" type="text/css">
</head>
<body>
  <main class="container">
    <header class="topbar">
      <h1 class="title">Piano AI</h1>
      <p>Train an AI model with your playing!</p>
      <div class="controls">
        <button class="play-button" id="play-toggle" type="button">Play</button>
        <a class="text-link" href="about.php">About</a>
      </div>
    </header>

    <section class="piano-card" aria-label="88-key piano">
      <div class="piano-range">
        <button class="range-button" id="range-previous" type="button" aria-label="Previous octave">‹</button>
        <span class="range-label" id="range-label" aria-live="polite"></span>
        <button class="range-button" id="range-next" type="button" aria-label="Next octave">›</button>
      </div>
      <div class="piano" id="piano" role="group" aria-label="Piano keys"></div>
    </section>

    <div class="melody" id="melody" aria-label="Recent notes"></div>
    <p class="status" id="status" aria-live="polite"></p>
  </main>
  <script src="model/piano_model.js"></script>
  <script src="js/application.js"></script>
</body>
</html>

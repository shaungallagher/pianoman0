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
  <meta name="description" content="About Piano AI and how its shared model learns.">
  <title>About — Piano AI</title>
  <link rel="icon" href="meta/piano-icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="meta/apple-touch-icon.png">
  <link href="style/main.css" rel="stylesheet" type="text/css">
</head>
<body>
  <main class="container">
    <header class="topbar about-topbar">
      <h1 class="title">About</h1>
      <a class="text-link" href="index.php">Back to piano</a>
    </header>

    <article class="about-copy">
      <p>Piano AI is an 88-key instrument, from A0 to C8, that learns from people playing it. When playing, its model looks at up to four recent notes to suggest what might come next, then playback continues using notes you have played on your own piano.</p>
      <p>The shared model has learned from <strong id="shared-examples">…</strong> note patterns so far. Contributions are limited to 50,000 examples per day.</p>
      <p>You can play the piano directly or use <kbd>A W S E D F T G Y H U J K</kbd> around middle C. The octave controls move across the full range.</p>
      <p>This is a passion project made by <a href="https://www.pianoman0.com" target="_blank" rel="noopener noreferrer">PianoMan0</a>!</p>
    </article>
  </main>
  <script>
    (function () {
      "use strict";

      var examples = document.getElementById("shared-examples");

      window.fetch("./api/model.php", { cache: "no-store" })
        .then(function (response) {
          if (!response.ok) throw new Error("Shared model is unavailable.");
          return response.json();
        })
        .then(function (model) {
          if (!Number.isSafeInteger(model.examples) || model.examples < 0) {
            throw new Error("Shared model statistics are invalid.");
          }
          examples.textContent = model.examples.toLocaleString();
        })
        .catch(function () {
          examples.textContent = "currently unavailable";
        });
    }());
  </script>
</body>
</html>

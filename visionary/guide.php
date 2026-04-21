<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ideas Guide - Visionary</title>
    <link rel="stylesheet" href="<?=asset_url('css/style.css')?>">
    <style>
        .guide-container { max-width: 800px; margin: 24px auto; padding: 0 16px; }
        .guide-section { background: var(--card); padding: 24px; border-radius: 6px; margin-bottom: 16px; }
        .guide-section h2 { margin-top: 0; color: var(--accent); font-size: 20px; }
        .guide-section p { line-height: 1.6; color: #333; }
        body.dark .guide-section p { color: #aaa; }
        .example-box { background: rgba(102, 126, 234, 0.05); padding: 16px; border-left: 3px solid var(--accent); border-radius: 4px; margin: 12px 0; font-size: 14px; font-family: monospace; }
        .tip-box { background: rgba(34, 197, 94, 0.05); padding: 16px; border-left: 3px solid #22c55e; border-radius: 4px; margin: 12px 0; }
        .tip-box strong { color: #22c55e; }
        .checklist { list-style: none; padding: 0; }
        .checklist li { padding: 8px 0; padding-left: 24px; position: relative; }
        .checklist li::before { content: '✓'; position: absolute; left: 0; color: var(--accent); font-weight: bold; }
        .faq-item { margin-bottom: 16px; }
        .faq-item strong { display: block; margin-bottom: 4px; color: var(--accent); cursor: pointer; }
        .faq-item p { margin: 0; font-size: 14px; color: var(--muted); }
    </style>
</head>
<body>
    <header>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px">
            <div>
                <h1 style="margin:0">📚 Ideas Guide</h1>
                <p style="margin:0;opacity:0.9">How to post great ideas</p>
            </div>
            <div style="display:flex;gap:12px;align-items:center">
                <button id="themeToggle" title="Toggle dark mode" style="background:transparent;border:0;color:#fff;font-size:18px;cursor:pointer">🌙</button>
                <a href="ideas.php" style="color:#fff;text-decoration:none;padding:8px 16px;background:rgba(255,255,255,0.1);border-radius:4px;">← Ideas</a>
            </div>
        </div>
    </header>

    <main>
        <div class="guide-container">
            <div class="guide-section">
                <h2>Getting Started</h2>
                <p>Visionary is a platform where creative minds post innovative ideas and developers bring them to life. Whether you're sharing a groundbreaking app concept or a simple tool, here's how to make your idea shine.</p>
                <div class="tip-box">
                    <strong>Pro Tip:</strong> Clear, compelling ideas attract more developers. Spend time crafting your description!
                </div>
            </div>

            <div class="guide-section">
                <h2>Basic Structure</h2>
                <p>Every great idea on Visionary follows this structure:</p>
                
                <h3 style="color: var(--accent); font-size: 16px; margin-top: 0;">1. Title</h3>
                <p>Short, catchy, and descriptive (under 50 characters)</p>
                <div class="example-box">
                    ✓ "AI Meal Planner for Fitness Goals"<br>
                    ✗ "App idea"
                </div>

                <h3 style="color: var(--accent); font-size: 16px;">2. Description</h3>
                <p>Paint a picture of the idea in 2-3 paragraphs</p>
                <div class="example-box">
                    "An AI-powered app that learns user fitness goals<br>
                    and dietary preferences, then generates personalized<br>
                    meal plans with recipes and shopping lists. Includes<br>
                    nutrition tracking and social meal planning features."
                </div>

                <h3 style="color: var(--accent); font-size: 16px;">3. Tags</h3>
                <p>Help people discover your idea with relevant tags</p>
                <div class="example-box">
                    AI, Health, Mobile app, Fitness, React Native
                </div>
            </div>

            <div class="guide-section">
                <h2>Before You Post</h2>
                <p>Use this checklist to ensure your idea is ready:</p>
                <ul class="checklist">
                    <li>Title is clear and under 50 characters</li>
                    <li>Description explains the problem and solution</li>
                    <li>Added relevant tags for discoverability</li>
                    <li>Idea has a unique angle or improvement</li>
                    <li>No personally identifiable information (PII) shared</li>
                    <li>Description is free of spam/promotions</li>
                </ul>
            </div>

            <div class="guide-section">
                <h2>Pro Tips for Success</h2>
                <p><strong>Be Specific:</strong> Instead of "Productivity app", say "Habit tracker with AI-powered insights"</p>
                <p><strong>Include the Why:</strong> Explain the problem you're solving and why it matters</p>
                <p><strong>Think Implementation:</strong> Consider what technologies would work best</p>
                <p><strong>Be Realistic:</strong> Ideas should be challenging but achievable</p>
                <p><strong>Engage:</strong> Reply to developers who take interest in your idea</p>
            </div>

            <div class="guide-section">
                <h2>After Posting</h2>
                <p>Your idea doesn't end after posting! Here's what to do next:</p>
                <ul class="checklist">
                    <li>Check your <a href="dashboard.php" style="color: var(--accent);">dashboard</a> for updates</li>
                    <li>Respond to developer questions and feedback</li>
                    <li>Monitor engagement metrics</li>
                    <li>Collaborate with interested developers</li>
                    <li>Update status as progress is made</li>
                    <li>Share ideas on social media (coming soon!)</li>
                </ul>
            </div>

            <div class="guide-section">
                <h2>FAQ</h2>
                
                <div class="faq-item">
                    <strong>Q: Can I edit my idea after posting?</strong>
                    <p>Yes! You can update the description and tags anytime from your dashboard.</p>
                </div>

                <div class="faq-item">
                    <strong>Q: What if my idea is private/under NDA?</strong>
                    <p>Visionary is a public platform. Only post ideas you're comfortable sharing publicly. For confidential ideas, use a vague description that captures the essence without revealing specifics.</p>
                </div>

                <div class="faq-item">
                    <strong>Q: How do I measure success?</strong>
                    <p>Track likes, comments, and claims. Completed ideas are the ultimate success! Visit your dashboard to see your metrics.</p>
                </div>

            </div>

            <div class="guide-section">
                <h2>Guidelines</h2>
                <p><strong>Don't post:</strong></p>
                <ul style="margin: 8px 0;">
                    <li>Offensive, hateful, or discriminatory content</li>
                    <li>Commercial spam or promotional material</li>
                    <li>Duplicate ideas already on the platform</li>
                    <li>Personal information about others</li>
                    <li>Copyrighted material without permission</li>
                </ul>
                <p style="font-size: 12px; color: var(--muted); margin-top: 12px;">Ideas in violation of guidelines may be removed and accounts suspended.</p>
            </div>

            <div class="guide-section" style="background: rgba(102, 126, 234, 0.05); border: 1px solid var(--accent);">
                <h2 style="margin-top: 0;">🎉 Ready to Share?</h2>
                <p style="margin-bottom: 16px;">Start posting your ideas and connect with talented developers ready to build your vision!</p>
                <a href="ideas.php" style="display: inline-block; padding: 12px 24px; background: var(--accent); color: white; text-decoration: none; border-radius: 6px;">Post an Idea</a>
            </div>
        </div>
    </main>

    <script nonce="<?=$nonce?>">
    </script>
</body>
</html>
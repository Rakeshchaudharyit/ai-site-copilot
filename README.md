# AI Site Copilot – WordPress SEO Assistant

AI Site Copilot is an AI-powered WordPress plugin that helps website owners improve SEO and content quality directly from the WordPress admin dashboard.

The plugin scans posts and pages for common SEO issues, generates SEO metadata using AI, suggests internal links between related posts, and provides quick fixes to improve site optimization.

---

# Features

## 1. Site Analyzer
The Site Analyzer scans WordPress posts and pages to detect common SEO problems.

It identifies:

• Thin content  
• Missing excerpts  
• Missing featured images  
• Short SEO titles  
• Pages with no internal links  

The scan generates a summary report and highlights posts with the most issues.

Example output:

- Total checked posts/pages
- Thin content count
- Missing excerpt count
- Missing featured images
- Short titles
- No internal links

This helps site owners quickly understand the overall SEO health of their website.

---

## 2. SEO Fixer (AI-Powered)

The SEO Fixer uses OpenAI to automatically generate SEO metadata for a selected post or page.

Generated fields include:

• SEO Title  
• Meta Description  
• Focus Keyphrase  

These values are saved directly into Yoast SEO fields, making them immediately usable by the SEO plugin.

AI analyzes:

- Post title
- Content
- Existing excerpt

Then generates optimized metadata following SEO best practices.

Example output:

SEO Title  
Optimized WordPress AI Tools for SEO Growth

Meta Description  
Discover how AI can improve WordPress SEO with automated metadata, content optimization, and internal linking strategies.

Focus Keyphrase  
AI WordPress SEO tools

---

## 3. Internal Link Engine (AI)

The Internal Link Engine suggests internal links between posts to improve site structure and SEO.

AI analyzes the selected post and identifies related content from existing posts or pages.

The plugin generates suggestions containing:

• Anchor text  
• Target URL  
• Page title  
• Reason for linking  

Users can review suggestions and choose which links to insert into the content.

This helps:

- improve internal linking
- increase page views
- strengthen SEO authority between pages

---

## 4. Insert Internal Links

After reviewing suggestions, users can insert selected links directly into the post content.

The system:

• detects the anchor text in the content  
• inserts the internal link  
• prevents duplicate links  
• skips anchors not found in the post  

This ensures links are inserted safely without breaking existing content.

---

## 5. Quick Fix (One-Click Optimization)

The Quick Fix feature allows users to automatically fix SEO issues detected during the site scan.

Quick Fix can:

• Generate SEO metadata  
• Create an excerpt if missing  
• Improve SEO structure  

This allows quick optimization without manually editing each post.

System pages such as checkout or cart pages are automatically skipped to prevent accidental modification.

---

## 6. AI Usage Logging

All AI operations are logged in a database table.

Each log records:

- action type
- success or failure
- tokens used
- estimated cost
- message details

Example log entry:

Action: seo_fix  
Status: success  
Tokens Used: 218  
Cost Estimate: 0.00006 USD  

This helps track AI usage and monitor API costs.

---

# Technology Stack

The plugin is built using modern WordPress development practices.

Core technologies include:

WordPress REST API  
PHP (Object-Oriented Architecture)  
JavaScript (Admin Dashboard UI)  
OpenAI API  
MySQL Database Logging  

---

# Plugin Architecture

The plugin follows a modular architecture:

/includes  
/Admin – WordPress admin pages  
/AI – OpenAI integration and prompts  
/Database – log storage  
/Rest – REST API controllers  
/Services – site scanning and processing  

/assets  
/admin.js – dashboard functionality

This structure keeps the plugin maintainable and scalable.

---

# REST API Endpoints

The plugin exposes several custom REST endpoints:

POST /aisc/v1/test  
Tests OpenAI API configuration.

POST /aisc/v1/scan  
Runs the site analyzer.

POST /aisc/v1/seo-fix  
Generates SEO metadata for a post.

POST /aisc/v1/internal-links  
Generates AI internal link suggestions.

POST /aisc/v1/insert-links  
Inserts selected internal links into post content.

POST /aisc/v1/quick-fix  
Applies automatic SEO improvements to a post.

---

# Installation

1. Upload the plugin folder to:

wp-content/plugins/

2. Activate the plugin in WordPress admin.

3. Enter your OpenAI API key in the plugin settings.

4. Open the AI Site Copilot dashboard to start scanning your site.

---

# Requirements

WordPress 6.0 or higher  
PHP 7.4 or higher  
OpenAI API Key

---

# Future Improvements

Planned improvements include:

• Bulk SEO optimization  
• AI content improvement suggestions  
• AI featured image generation  
• Advanced internal link graph  
• Monthly AI cost analytics dashboard

---

# License

MIT License

---

# Author

Rakesh Chaudhary

WordPress Developer & AI Automation Engineer

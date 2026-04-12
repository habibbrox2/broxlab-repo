const express = require('express');
const axios = require('axios');
const cheerio = require('cheerio');

const app = express();
const port = 3001;

// Middleware
app.use(express.json());

// Basic scraping endpoint
app.post('/api/admin/ai-tools/execute', async (req, res) => {
  try {
    const { tool, args } = req.body;

    if (tool !== 'fetch_url_content') {
      return res.status(400).json({
        success: false,
        error: 'Unsupported tool',
      });
    }

    const { url, javascript = true, timeout = 30000 } = args;

    console.log(`Fetching URL: ${url}`);

    // Fetch the URL
    const response = await axios.get(url, {
      timeout,
      headers: {
        'User-Agent':
          'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'en-US,en;q=0.5',
        'Accept-Encoding': 'gzip, deflate',
        Connection: 'keep-alive',
        'Upgrade-Insecure-Requests': '1',
      },
      maxRedirects: 5,
    });

    const html = response.data;

    // Basic content extraction
    const $ = cheerio.load(html);
    const title = $('title').text().trim() || $('h1').first().text().trim() || 'No title found';
    const content = $('body').text().trim().substring(0, 5000); // Limit content length

    res.json({
      success: true,
      data: {
        html: html,
        title: title,
        content: content,
        url: url,
      },
    });
  } catch (error) {
    console.error('Scraping error:', error.message);
    res.status(500).json({
      success: false,
      error: error.message,
    });
  }
});

// Health check
app.get('/health', (req, res) => {
  res.json({ status: 'ok', service: 'basic-scraper', port });
});

// Start server
app.listen(port, () => {
  console.log(`Basic scraper service running on port ${port}`);
});

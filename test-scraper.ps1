# Test scraper with .env configuration
cd "h:\Web\broxlab"

Write-Host "🔍 Testing Scraper Configuration..." -ForegroundColor Cyan
Write-Host ""

# Test 1: Validate configuration
Write-Host "1️⃣  Validating configuration..." -ForegroundColor Yellow
node -e "
import('./src/scraper/config.js').then(m => {
  try {
    m.validateConfig();
    console.log('✅ Configuration VALID');
  } catch(e) {
    console.log('❌ Configuration ERROR:', e.message);
    process.exit(1);
  }
}).catch(e => {
  console.log('❌ Import ERROR:', e.message);
  process.exit(1);
});
" 2>&1

if ($LASTEXITCODE -ne 0) {
  Write-Host "Configuration validation failed!" -ForegroundColor Red
  exit 1
}

Write-Host ""
Write-Host "2️⃣  Checking logs directory..." -ForegroundColor Yellow
if (-Not (Test-Path "logs")) {
  New-Item -ItemType Directory -Path "logs" -Force | Out-Null
  Write-Host "✅ Created logs directory"
} else {
  Write-Host "✅ Logs directory exists"
}

Write-Host ""
Write-Host "3️⃣  Running scraper (single article, bdnews24)..." -ForegroundColor Yellow
node src/scraper/index.js --source=bdnews24 --max=1

Write-Host ""
Write-Host "4️⃣  Checking scraper log..." -ForegroundColor Yellow
if (Test-Path "logs/scraper.log") {
  Write-Host "✅ Log file created"
  Write-Host ""
  Write-Host "📋 Last 10 lines of scraper.log:" -ForegroundColor Cyan
  Get-Content "logs/scraper.log" -Tail 10
} else {
  Write-Host "⚠️  Log file not found yet"
}

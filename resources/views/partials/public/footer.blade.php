@php use App\Support\LanguageService; @endphp
<footer class="relative overflow-hidden border-t border-slate-800/70 bg-slate-950 py-2 text-slate-300 shadow-[0_-8px_30px_rgba(15,23,42,0.12)] sm:py-3 md:py-4" role="contentinfo" itemscope itemtype="https://schema.org/WPFooter">
  <div class="pointer-events-none absolute inset-0">
    <div class="absolute -top-24 left-1/4 h-56 w-56 rounded-full bg-indigo-500/10 blur-3xl"></div>
    <div class="absolute -bottom-24 right-1/4 h-56 w-56 rounded-full bg-cyan-400/10 blur-3xl"></div>
  </div>
  <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8 md:grid-cols-3 md:gap-10 mb-8 md:mb-12">

      {{-- About Company --}}
      <div class="md:col-span-1">
        <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-indigo-200">
          <i class="lucide lucide-sparkles h-3.5 w-3.5" aria-hidden="true"></i> About
        </div>
        <div class="mb-3 text-lg font-bold text-white" itemprop="name">
          <i class="lucide lucide-smartphone mr-2" aria-hidden="true"></i>{{ $appSettings['site_name'] ?? 'BroxLab' }}
        </div>
        <p class="footer-text text-sm leading-7 text-slate-400" itemprop="description">
          {{ $appSettings['meta_description'] ?? 'Your trusted source for mobile reviews, tech insights, and online services. Connect with us on social media for exclusive content and the latest updates.' }}
        </p>

        {{-- Social Media Links --}}
        @if(!empty($appSettings['social_twitter']) || !empty($appSettings['social_instagram']) || !empty($appSettings['social_facebook']))
          <nav aria-label="Social media links" class="mt-4">
            <div class="flex gap-4 footer-social-links">
              @if(!empty($appSettings['social_twitter']))
                <a href="{{ $appSettings['social_twitter'] }}"
                   class="text-slate-400 hover:text-indigo-500 transition-all duration-200 rounded-lg p-2 hover:bg-white/5"
                   title="Follow us on Twitter/X"
                   rel="noopener noreferrer"
                   target="_blank"
                   aria-label="Twitter/X">
                  <i class="lucide lucide-share-2 text-lg" aria-hidden="true"></i>
                </a>
              @endif
              @if(!empty($appSettings['social_instagram']))
                <a href="{{ $appSettings['social_instagram'] }}"
                   class="text-slate-400 hover:text-indigo-500 transition-all duration-200 rounded-lg p-2 hover:bg-white/5"
                   title="Follow us on Instagram"
                   rel="noopener noreferrer"
                   target="_blank"
                   aria-label="Instagram">
                  <i class="lucide lucide-instagram text-lg" aria-hidden="true"></i>
                </a>
              @endif
              @if(!empty($appSettings['social_facebook']))
                <a href="{{ $appSettings['social_facebook'] }}"
                   class="text-slate-400 hover:text-indigo-500 transition-all duration-200 rounded-lg p-2 hover:bg-white/5"
                   title="Follow us on Facebook"
                   rel="noopener noreferrer"
                   target="_blank"
                   aria-label="Facebook">
                  <i class="lucide lucide-share-2 text-lg" aria-hidden="true"></i>
                </a>
              @endif
            </div>
          </nav>
        @endif
      </div>

      {{-- Quick Links --}}
      <div class="md:col-span-1">
        <div class="mb-4 text-sm font-bold uppercase tracking-[0.2em] text-white">Quick Links</div>
        <ul class="space-y-3 text-sm" role="navigation" aria-label="Quick navigation">
          <li class="list-none">
            <a href="/about-us" class="text-slate-400 hover:text-white transition-all duration-200 inline-flex items-center gap-2 hover:translate-x-1">
              <i class="lucide lucide-chevron-right text-xs" aria-hidden="true"></i>
              <span>About Us</span>
            </a>
          </li>
          <li class="list-none">
            <a href="/contact" class="text-slate-400 hover:text-white transition-all duration-200 inline-flex items-center gap-2 hover:translate-x-1">
              <i class="lucide lucide-chevron-right text-xs" aria-hidden="true"></i>
              <span>Contact</span>
            </a>
          </li>
          <li class="list-none">
            <a href="/newsletter" class="text-slate-400 hover:text-white transition-all duration-200 inline-flex items-center gap-2 hover:translate-x-1">
              <i class="lucide lucide-chevron-right text-xs" aria-hidden="true"></i>
              <span>Newsletter</span>
            </a>
          </li>
          <li class="list-none">
            <a href="/advertise" class="text-slate-400 hover:text-white transition-all duration-200 inline-flex items-center gap-2 hover:translate-x-1">
              <i class="lucide lucide-chevron-right text-xs" aria-hidden="true"></i>
              <span>Advertise</span>
            </a>
          </li>
          <li class="list-none">
            <a href="/faq" class="text-slate-400 hover:text-white transition-all duration-200 inline-flex items-center gap-2 hover:translate-x-1">
              <i class="lucide lucide-chevron-right text-xs" aria-hidden="true"></i>
              <span>FAQ</span>
            </a>
          </li>
        </ul>
      </div>

      {{-- Legal & Compliance --}}
      <div class="md:col-span-1">
        <div class="mb-4 text-sm font-bold uppercase tracking-[0.2em] text-white">Legal</div>
        <ul class="space-y-3 text-sm" role="navigation" aria-label="Legal navigation">
          <li class="list-none">
            <a href="/terms" class="text-slate-400 hover:text-white transition-all duration-200 inline-flex items-center gap-2 hover:translate-x-1">
              <i class="lucide lucide-chevron-right text-xs" aria-hidden="true"></i>
              <span>Terms of Service</span>
            </a>
          </li>
          <li class="list-none">
            <a href="/privacy" class="text-slate-400 hover:text-white transition-all duration-200 inline-flex items-center gap-2 hover:translate-x-1">
              <i class="lucide lucide-chevron-right text-xs" aria-hidden="true"></i>
              <span>Privacy Policy</span>
            </a>
          </li>
          <li class="list-none">
            <a href="/sitemap" class="text-slate-400 hover:text-white transition-all duration-200 inline-flex items-center gap-2 hover:translate-x-1">
              <i class="lucide lucide-chevron-right text-xs" aria-hidden="true"></i>
              <span>Sitemap</span>
            </a>
          </li>
          <li class="list-none">
            <a href="/sitemap.xml" class="text-slate-400 hover:text-white transition-all duration-200 inline-flex items-center gap-2 hover:translate-x-1">
              <i class="lucide lucide-chevron-right text-xs" aria-hidden="true"></i>
              <span>XML Sitemap</span>
            </a>
          </li>
        </ul>
      </div>
    </div>

    {{-- Footer Divider --}}
    <hr class="border-slate-700/50 opacity-50" role="doc-pagebreak" aria-hidden="true">

    {{-- Footer Bottom: Copyright & Contact --}}
    <div class="mt-6 flex flex-col gap-3 text-center sm:flex-row sm:items-center sm:justify-between md:text-left">
      <p class="footer-copyright text-sm text-slate-400">
        &copy; {{ date('Y') }} <strong class="text-white">{{ $appSettings['site_name'] ?? 'BroxLab' }}</strong>. All rights reserved.
        @if(!empty($appSettings['contact_email']))
          | <a href="mailto:{{ $appSettings['contact_email'] }}"
               class="text-slate-400 hover:text-white transition-colors"
               title="Email us">{{ $appSettings['contact_email'] }}</a>
        @endif
        <span class="footer-tagline ml-2 inline-block text-slate-500">Designed for maximum performance & accessibility.</span>
      </p>

      {{-- Language Switcher --}}
      @if(isset($availableLanguages) && count($availableLanguages) > 1)
        <div class="flex items-center gap-2">
          @if(app(LanguageService::class)->current() === 'bn')
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40" class="h-4 w-4 rounded-sm shadow-sm" aria-hidden="true">
              <rect width="60" height="40" fill="#006a4e"/>
              <circle cx="23" cy="20" r="12" fill="#f42a41"/>
            </svg>
          @else
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40" class="h-4 w-4 rounded-sm shadow-sm" aria-hidden="true">
              <rect width="60" height="40" fill="#fff"/>
              <g fill="#b22234">
                <rect y="0" width="60" height="3.08"/><rect y="6.15" width="60" height="3.08"/>
                <rect y="12.31" width="60" height="3.08"/><rect y="18.46" width="60" height="3.08"/>
                <rect y="24.62" width="60" height="3.08"/><rect y="30.77" width="60" height="3.08"/>
                <rect y="36.92" width="60" height="3.08"/>
              </g>
              <rect width="24" height="21.54" fill="#3c3b6e"/>
            </svg>
          @endif
          <span class="text-xs text-slate-500">Language</span>
          <button type="button"
                  data-lang-btn="{{ app(LanguageService::class)->current() == 'bn' ? 'en' : 'bn' }}"
                  aria-label="{{ app(LanguageService::class)->current() == 'bn' ? 'Switch to English' : 'Switch to Bangla' }}"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800/50 px-3 py-1.5 text-xs font-medium text-slate-300 transition-all duration-200 hover:border-indigo-500/50 hover:bg-slate-800 hover:text-white hover:shadow-lg hover:shadow-indigo-500/10">
            @if(app(LanguageService::class)->current() === 'bn')
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40" class="h-3.5 w-5 rounded-sm shadow-sm" aria-hidden="true">
                <rect width="60" height="40" fill="#006a4e"/>
                <circle cx="23" cy="20" r="12" fill="#f42a41"/>
              </svg>
              বাংলা
            @else
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40" class="h-3.5 w-5 rounded-sm shadow-sm" aria-hidden="true">
                <rect width="60" height="40" fill="#fff"/>
                <g fill="#b22234">
                  <rect y="0" width="60" height="3.08"/><rect y="6.15" width="60" height="3.08"/>
                  <rect y="12.31" width="60" height="3.08"/><rect y="18.46" width="60" height="3.08"/>
                  <rect y="24.62" width="60" height="3.08"/><rect y="30.77" width="60" height="3.08"/>
                  <rect y="36.92" width="60" height="3.08"/>
                </g>
                <rect width="24" height="21.54" fill="#3c3b6e"/>
              </svg>
              English
            @endif
          </button>
        </div>
      @endif
    </div>
  </div>
</footer>

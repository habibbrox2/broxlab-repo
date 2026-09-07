# BroxLab CV Template Collection

A premium collection of **37 professionally designed CV/Resume templates** built for the BroxLab CV Builder platform. Each template is a fully standalone HTML document ready for integration with Twig/PHP.

## Overview

| Feature | Details |
|---------|---------|
| **Total Templates** | 37 unique designs |
| **Format** | HTML5 + Tailwind CSS (CDN) |
| **Print Ready** | A4 (210mm × 297mm) optimized |
| **Responsive** | Desktop, Tablet, Mobile |
| **Icons** | Inline SVG (Lucide-compatible) |
| **Fonts** | Google Fonts (Inter, Playfair Display, etc.) |
| **Dependencies** | None (Tailwind via CDN) |

## Template Categories

| Category | Count | Examples |
|----------|-------|---------|
| **Minimal** | 4 | Minimal White, Nordic, Swiss Design, Apple Style, Japanese Minimal |
| **Modern** | 6 | Modern Blue, Timeline Modern, Material Design, Tech Startup, Double Column, Sidebar Right |
| **Dark** | 3 | Dark Professional, Premium Black, Developer Portfolio |
| **Professional** | 6 | Corporate Green, Healthcare, Education Teacher, Hospitality, Construction, Engineering, Architect |
| **Corporate** | 4 | Executive, Legal Professional, Finance & Banking |
| **Luxury** | 2 | Elegant Gold, Luxury |
| **Creative** | 5 | Creative Purple, Glassmorphism, Magazine Layout, Bold Gradient, Creative Artist, Diagonal Dynamic |
| **Executive** | 1 | Sales & Marketing |

## Template Structure

```
templates/
├── template-name/
│   └── index.html          # Main template file
├── templates.json           # Metadata for all templates
└── README.md               # This file
```

## Design Philosophy

Each template was designed with a unique visual identity:

- **No duplicates** — Every template has a distinct layout, color palette, typography hierarchy, and component design
- **ATS-friendly** — Clean, parseable HTML structure with semantic section markers
- **Print-optimized** — CSS `@page` rules with `print-color-adjust: exact` for perfect A4 output
- **Twig-ready** — Every data section is wrapped with `<!-- START -->` / `<!-- END -->` comments for easy PHP/Twig integration

## Available Sections

Every template supports these sections with proper markers:

- ✅ Profile Photo (placeholder SVG)
- ✅ Full Name & Professional Title
- ✅ Professional Summary
- ✅ Contact Information (email, phone, location)
- ✅ Social Links (LinkedIn, GitHub, Portfolio/Website)
- ✅ Technical Skills (progress bars)
- ✅ Soft Skills (tag/chip style)
- ✅ Work Experience (timeline/card layout)
- ✅ Education
- ✅ Key Projects
- ✅ Certificates
- ✅ Awards & Achievements
- ✅ Languages
- ✅ Interests
- ✅ References
- ✅ QR Code Placeholder

## Print Support

All templates include:

```css
@media print {
    @page { size: A4; margin: 0; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
}
```

- Perfect A4 (210mm × 297mm) rendering
- No clipping, overflow, or broken pages
- Colors preserved exactly as designed
- Hide interactive elements (`.no-print` class)

## Customization

### Colors
Each template defines custom colors via Tailwind config. Edit the `tailwind.config` colors block to change the palette.

### Fonts
Google Fonts are loaded via `<link>` tags in the `<head>`. Change the font family in both the Google Fonts URL and the `tailwind.config` `fontFamily` extension.

### Content
All dynamic content is wrapped in HTML comments:
```html
<!-- START EXPERIENCE -->
...
<!-- END EXPERIENCE -->
```

Replace the content between these markers with Twig variables or dynamic data.

## Usage

1. Include the template's `index.html` content in your Twig/PHP rendering
2. Replace the data between `<!-- START -->` / `<!-- END -->` markers
3. Keep the `<style>` and `<script>` blocks as-is for proper styling

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Print/PDF export in all modern browsers

## License

Part of the BroxLab project. All rights reserved.

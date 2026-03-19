# CV Builder Twig Templates

## Template Structure

### 1. Dashboard (List of CVs)
**File:** `app/Views/cv/dashboard.twig`
- List all CVs for the user
- Quick actions: Create new, Edit, Delete, Preview, Export, Share
- Show CV title, last updated date, and status (draft/published)

### 2. Editor (Split View)
**File:** `app/Views/cv/editor.twig`
- Left panel: Form for editing CV sections and items
- Right panel: Real-time preview
- Mobile: Toggle between form and preview
- Dynamic sections with add/remove/reorder functionality
- Auto-save indicator

### 3. CV Section Templates
**File:** `app/Views/cv/partials/section_*.twig`
- `section_summary.twig` - Professional summary
- `section_experience.twig` - Work experience
- `section_education.twig` - Education history
- `section_skills.twig` - Skills (with proficiency levels)
- `section_projects.twig` - Projects
- `section_certifications.twig` - Certifications

### 4. CV Item Templates
**File:** `app/Views/cv/partials/item_*.twig`
- `item_experience.twig` - Job entry
- `item_education.twig` - Degree entry
- `item_skill.twig` - Skill entry
- `item_project.twig` - Project entry
- `item_certification.twig` - Certification entry

### 5. CV Preview Templates (For rendering and PDF)
**File:** `app/Views/cv/templates/modern.twig`
- Modern template design
- Clean layout with accent colors

**File:** `app/Views/cv/templates/minimal.twig`
- Minimal template design
- Simple black and white

**File:** `app/Views/cv/templates/ats.twig`
- ATS-friendly template
- Simple formatting, no tables, standard fonts

## Frontend JavaScript

### Real-time Preview
- Use debounced API calls (e.g., 300ms delay) to fetch preview
- Update preview on section/item changes
- Toggle between form and preview on mobile

### Dynamic Sections
- Drag and drop for section reordering (using SortableJS or native HTML5 API)
- Add/remove sections dynamically
- Toggle section visibility

### Auto-save
- Save draft every 3-5 seconds
- Show "Saved" indicator
- Only save if there are changes (diff-based)

### AI Features Integration
- "Improve" button for each text field
- "ATS Score" button to analyze CV
- "Keyword Extract" for job description matching
- Loading indicators for AI operations

## Template Variables

### Dashboard
```twig
{
  cvs: [
    { id, title, updated_at, is_active },
    ...
  ],
  page_title: 'My CVs'
}
```

### Editor
```twig
{
  cv: {
    id,
    title,
    sections: [
      {
        id,
        section_type,
        title,
        order,
        is_visible,
        items: [
          {
            id,
            item_type,
            content_json,
            order
          },
          ...
        ]
      },
      ...
    ]
  },
  templates: ['modern', 'minimal', 'ats'],
  selected_template: 'modern',
  is_preview: false
}
```

### Preview (Template)
```twig
{
  cv: { ... },
  template: 'modern',
  is_public: false
}
```

## CSS Classes

Use Tailwind CSS for styling (as per project conventions). Example classes:
- `grid`, `grid-cols-2`, `gap-4` for split layout
- `p-4`, `m-4`, `rounded-lg` for spacing and borders
- `bg-white`, `text-gray-900` for colors
- `hover:bg-gray-100` for interactions

## Responsive Design

- Desktop: Split view (50% form, 50% preview)
- Tablet: Split view (40% form, 60% preview)
- Mobile: Toggle between form and preview (full width)
- Use `hidden md:block` and `md:hidden` classes for responsive behavior
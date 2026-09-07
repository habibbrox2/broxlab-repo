@php
    // Blade port of app/Views/partials/editor.twig — keeps the exact DOM
    // contract the RTE bundle expects (auto-init scans [id$="-wrapper"][data-rtl];
    // toolbar uses data-command / data-action; modals close via data-modal).
    $editorId = $editorId ?? 'richTextEditor';
    $editorName = $editorName ?? 'editor_content';
    $initialContent = $initialContent ?? '';
    $placeholder = $placeholder ?? 'Start typing here...';
    $minHeight = $minHeight ?? 120;
    $maxHeight = $maxHeight ?? 2000;
    $autoGrow = $autoGrow ?? true;
    $uploadUrl = $uploadUrl ?? '/upload';
@endphp

<div class="rte-container view-compose rte-ltr" id="{{ $editorId }}-wrapper"
     data-rtl="false"
     data-auto-grow="{{ $autoGrow ? 'true' : 'false' }}"
     data-min-height="{{ $minHeight }}"
     data-max-height="{{ $maxHeight }}"
     data-upload-url="{{ $uploadUrl }}">

    {{-- Hidden input for form submission --}}
    <input type="hidden" name="{{ $editorName }}" id="{{ $editorId }}-input" class="rte-hidden-input" value="{!! $initialContent !!}">

    {{-- Editor Area --}}
    <div class="rte-editor-wrapper">
        <div class="rte-compose-view">
            <div class="rte-editor"
                 id="{{ $editorId }}"
                 contenteditable="true"
                 data-placeholder="{{ $placeholder }}"
                 aria-label="Rich text editor"
                 aria-multiline="true"
                 spellcheck="true">{!! $initialContent !!}</div>
        </div>

        {{-- HTML Source View --}}
        <textarea class="rte-source-view" id="{{ $editorId }}-source" style="display: none;" aria-label="HTML source code view"></textarea>

        {{-- History View --}}
        <div class="rte-history-view" id="{{ $editorId }}-history" style="display: none;">
            <div class="rte-history-list"></div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="rte-toolbar" id="{{ $editorId }}-toolbar" role="toolbar" aria-label="Text formatting toolbar" aria-controls="{{ $editorId }}">

        {{-- Responsive Toggle --}}
        <div class="rte-toolbar-section">
            <button type="button" class="rte-btn rte-toolbar-responsive-toggle" title="Toggle toolbar" aria-label="Toggle toolbar" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">menu</i>
            </button>
        </div>

        <div class="rte-toolbar-divider"></div>

        {{-- Undo/Redo --}}
        <div class="rte-toolbar-section rte-undo-redo rte-toolbar-essential">
            <button type="button" class="rte-btn" title="Undo (Ctrl+Z)" data-command="undo" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">undo</i><span class="rte-tooltip">Undo <kbd>Ctrl+Z</kbd></span>
            </button>
            <button type="button" class="rte-btn" title="Redo (Ctrl+Y)" data-command="redo" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">redo</i><span class="rte-tooltip">Redo <kbd>Ctrl+Y</kbd></span>
            </button>
        </div>

        <div class="rte-toolbar-divider"></div>

        {{-- Text Style --}}
        <div class="rte-toolbar-section rte-text-style">
            <select class="rte-select rte-font-select" title="Font family" data-command="fontName" data-editor="{{ $editorId }}">
                <option value="Arial">Arial</option>
                <option value="Roboto">Roboto</option>
                <option value="Georgia">Georgia</option>
                <option value="Times New Roman" selected>Times New Roman</option>
                <option value="Courier New">Courier New</option>
                <option value="Verdana">Verdana</option>
                <option value="Noto Sans Bengali">নটো স্‌্যান্স বাংলা</option>
            </select>

            <input list="{{ $editorId }}-font-sizes" class="rte-select rte-font-size-select" title="Font size (px)"
                   data-command="fontSize" data-editor="{{ $editorId }}" type="text" inputmode="numeric" value="12">
            <datalist id="{{ $editorId }}-font-sizes">
                @foreach ([8, 10, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48, 72] as $size)
                    <option value="{{ $size }}">{{ $size }}px</option>
                @endforeach
            </datalist>

            <select id="{{ $editorId }}-heading-select" class="rte-select rte-heading-select" title="Block format" aria-label="Block format" data-editor="{{ $editorId }}">
                <option value="p">Paragraph</option>
                <option value="h1">Heading 1</option>
                <option value="h2">Heading 2</option>
                <option value="h3">Heading 3</option>
                <option value="h4">Heading 4</option>
                <option value="h5">Heading 5</option>
                <option value="h6">Heading 6</option>
            </select>

            <button type="button" class="rte-btn" title="Remove formatting" data-command="removeFormat" data-editor="{{ $editorId }}" aria-label="Remove formatting">
                <i class="rte-icon material-icons">format_clear</i><span class="rte-tooltip">Normal</span>
            </button>
        </div>

        <div class="rte-toolbar-divider"></div>

        {{-- Formatting --}}
        <div class="rte-toolbar-section rte-formatting rte-toolbar-essential">
            <button type="button" class="rte-btn" title="Bold (Ctrl+B)" data-command="bold" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">format_bold</i><span class="rte-tooltip">Bold <kbd>Ctrl+B</kbd></span>
            </button>
            <button type="button" class="rte-btn" title="Italic (Ctrl+I)" data-command="italic" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">format_italic</i><span class="rte-tooltip">Italic <kbd>Ctrl+I</kbd></span>
            </button>
            <button type="button" class="rte-btn" title="Underline (Ctrl+U)" data-command="underline" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">format_underlined</i><span class="rte-tooltip">Underline <kbd>Ctrl+U</kbd></span>
            </button>
            <button type="button" class="rte-btn" title="Strikethrough" data-command="strikeThrough" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">strikethrough_s</i><span class="rte-tooltip">Strikethrough</span>
            </button>

            <div class="rte-color-wrapper">
                <input type="color" id="{{ $editorId }}-text-color" class="rte-color-input rte-text-color-input" data-command="foreColor" data-editor="{{ $editorId }}" value="#000000" title="Click to change text color" aria-label="Text color">
                <button type="button" class="rte-color-label" title="Text color" aria-label="Text color picker">
                    <i class="rte-icon material-icons">format_color_text</i>
                </button>
            </div>

            <div class="rte-color-wrapper">
                <input type="color" id="{{ $editorId }}-bg-color" class="rte-color-input rte-bg-color-input" data-command="backColor" data-editor="{{ $editorId }}" value="#FFFF00" title="Click to change background color" aria-label="Background color">
                <button type="button" class="rte-color-label" title="Background color" aria-label="Background color picker">
                    <i class="rte-icon material-icons">format_color_fill</i>
                </button>
            </div>
        </div>

        <div class="rte-toolbar-divider"></div>

        {{-- Insert --}}
        <div class="rte-toolbar-section rte-insert rte-toolbar-essential">
            <button type="button" class="rte-btn" title="Insert/Edit Link" data-action="insertLink" aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $editorId }}-link-modal" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">link</i><span class="rte-tooltip">Link</span>
            </button>
            <button type="button" class="rte-btn" title="Insert Image" data-action="insertImage" aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $editorId }}-image-modal" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">image</i><span class="rte-tooltip">Image</span>
            </button>
            <button type="button" class="rte-btn" title="Insert Video" data-action="insertVideo" aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $editorId }}-video-modal" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">smart_display</i><span class="rte-tooltip">Video</span>
            </button>
            <button type="button" class="rte-btn" title="Special characters" data-action="insertSpecialChar" aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $editorId }}-special-char-modal" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">functions</i><span class="rte-tooltip">Char</span>
            </button>
        </div>

        <div class="rte-toolbar-divider"></div>

        {{-- Alignment --}}
        <div class="rte-toolbar-section rte-alignment">
            <button type="button" class="rte-btn" title="Left align" data-command="justifyLeft" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">format_align_left</i><span class="rte-tooltip">Left</span>
            </button>
            <button type="button" class="rte-btn" title="Center align" data-command="justifyCenter" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">format_align_center</i><span class="rte-tooltip">Center</span>
            </button>
            <button type="button" class="rte-btn" title="Right align" data-command="justifyRight" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">format_align_right</i><span class="rte-tooltip">Right</span>
            </button>
            <button type="button" class="rte-btn" title="Justify" data-command="justifyFull" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">format_align_justify</i><span class="rte-tooltip">Justify</span>
            </button>
            <button type="button" class="rte-btn" title="Increase indent" data-command="indent" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">format_indent_increase</i><span class="rte-tooltip">Indent</span>
            </button>
            <button type="button" class="rte-btn" title="Decrease indent" data-command="outdent" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">format_indent_decrease</i><span class="rte-tooltip">Outdent</span>
            </button>
        </div>

        <div class="rte-toolbar-divider"></div>

        {{-- Lists & Blocks --}}
        <div class="rte-toolbar-section rte-lists">
            <button type="button" class="rte-btn" title="Bulleted list" data-command="insertUnorderedList" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">format_list_bulleted</i><span class="rte-tooltip">Bullet list</span>
            </button>
            <button type="button" class="rte-btn" title="Numbered list" data-command="insertOrderedList" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">format_list_numbered</i><span class="rte-tooltip">Number list</span>
            </button>
            <button type="button" class="rte-btn" title="Quote text" data-command="formatBlock" data-value="blockquote" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">format_quote</i><span class="rte-tooltip">Quote</span>
            </button>
            <button type="button" class="rte-btn" title="Insert horizontal rule" data-command="insertHorizontalRule" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">horizontal_rule</i><span class="rte-tooltip">HR</span>
            </button>
        </div>

        <div class="rte-toolbar-divider"></div>

        {{-- Other --}}
        <div class="rte-toolbar-section rte-other">
            <button type="button" class="rte-btn" title="Clear formatting" data-action="clearFormatting" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons">clear_all</i><span class="rte-tooltip">Clear</span>
            </button>
            <button type="button" class="rte-btn" title="Toggle fullscreen (Ctrl+Shift+F)" data-action="toggleFullscreen" data-editor="{{ $editorId }}">
                <i class="rte-icon material-icons rte-fullscreen-icon">fullscreen</i><span class="rte-tooltip">Fullscreen <kbd>Ctrl+Shift+F</kbd></span>
            </button>
            <div class="rte-more-dropdown">
                <button type="button" class="rte-btn" title="More options" data-action="toggleMore" data-editor="{{ $editorId }}">
                    <i class="rte-icon material-icons">more_vert</i><span class="rte-tooltip">More</span>
                </button>
                <div class="rte-more-menu" role="menu" style="display: none;">
                    <div class="rte-more-item" data-action="insertTable" role="menuitem" tabindex="-1"><i class="rte-icon material-icons">table_chart</i> Insert Table</div>
                    <div class="rte-more-item" data-action="insertCodeBlock" role="menuitem" tabindex="-1"><i class="rte-icon material-icons">code_off</i> Code Block</div>
                    <div class="rte-more-item" data-action="selectAll" role="menuitem" tabindex="-1"><i class="rte-icon material-icons">select_all</i> Select All</div>
                    <div class="rte-more-item" data-action="showWordCount" role="menuitem" tabindex="-1"><i class="rte-icon material-icons">data_usage</i> Word Count</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Link Modal --}}
    <div class="rte-modal rte-link-modal" id="{{ $editorId }}-link-modal" style="display: none;" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="{{ $editorId }}-link-modal-title">
        <div class="rte-modal-content">
            <div class="rte-modal-header">
                <h3 id="{{ $editorId }}-link-modal-title" data-rte-modal-title tabindex="-1">Insert Link</h3>
                <button type="button" class="rte-modal-close" data-editor="{{ $editorId }}" data-modal="link" aria-label="Close">&times;</button>
            </div>
            <div class="rte-modal-body">
                <input type="text" placeholder="Enter URL" class="rte-modal-input rte-link-url" value="https://">
                <input type="text" placeholder="Link text (optional)" class="rte-modal-input rte-link-text">
                <label class="rte-checkbox">
                    <input type="checkbox" class="rte-link-target">
                    <span>Open in new window</span>
                </label>
            </div>
            <div class="rte-modal-footer">
                <button type="button" class="rte-btn-cancel" data-editor="{{ $editorId }}" data-modal="link">Cancel</button>
                <button type="button" class="rte-btn-submit rte-link-submit" data-editor="{{ $editorId }}">Insert Link</button>
            </div>
        </div>
    </div>

    {{-- Image Modal --}}
    <div class="rte-modal rte-image-modal" id="{{ $editorId }}-image-modal" style="display: none;" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="{{ $editorId }}-image-modal-title">
        <div class="rte-modal-content">
            <div class="rte-modal-header">
                <h3 id="{{ $editorId }}-image-modal-title" data-rte-modal-title tabindex="-1">Insert Image</h3>
                <button type="button" class="rte-modal-close" data-editor="{{ $editorId }}" data-modal="image" aria-label="Close">&times;</button>
            </div>
            <div class="rte-modal-body">
                <div class="rte-image-tabs">
                    <button type="button" class="rte-image-tab-btn active" data-tab="url">URL</button>
                    <button type="button" class="rte-image-tab-btn" data-tab="upload">Upload</button>
                </div>

                <div class="rte-image-tab-content rte-image-tab-url-content">
                    <input type="text" placeholder="Enter image URL" class="rte-modal-input rte-image-url" value="https://">
                    <div><img class="rte-image-preview" src="" alt="Preview" style="display: none;"></div>
                </div>

                <div class="rte-image-tab-content rte-image-tab-upload-content" style="display: none;">
                    <div class="rte-dropzone" data-editor="{{ $editorId }}">
                        <input type="file" class="rte-file-input" accept="image/*" style="display: none;" aria-label="Upload image">
                        <p>Drag and drop image here or click to browse</p>
                    </div>
                    <div class="rte-image-preview-area" style="display:none;">
                        <img class="rte-image-preview" src="" alt="Preview">
                        <div class="rte-image-preview-actions">
                            <button type="button" class="rte-btn-cancel" id="{{ $editorId }}-image-cancel">Cancel</button>
                            <button type="button" class="rte-btn-submit" id="{{ $editorId }}-image-insert">Insert Image</button>
                        </div>
                    </div>
                </div>

                <div>
                    <label>Width:</label>
                    <input type="text" placeholder="e.g., 300px or 100%" class="rte-modal-input rte-image-width">
                </div>
                <div>
                    <label>Height:</label>
                    <input type="text" placeholder="e.g., 200px or auto" class="rte-modal-input rte-image-height">
                </div>
                <div>
                    <label>Alt text:</label>
                    <input type="text" placeholder="Image description" class="rte-modal-input rte-image-alt">
                </div>
            </div>
            <div class="rte-modal-footer">
                <button type="button" class="rte-btn-cancel" data-editor="{{ $editorId }}" data-modal="image">Cancel</button>
                <button type="button" class="rte-btn-submit rte-image-submit" data-editor="{{ $editorId }}">Insert Image</button>
            </div>
        </div>
    </div>

    {{-- Video Modal --}}
    <div class="rte-modal rte-video-modal" id="{{ $editorId }}-video-modal" style="display: none;" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="{{ $editorId }}-video-modal-title">
        <div class="rte-modal-content">
            <div class="rte-modal-header">
                <h3 id="{{ $editorId }}-video-modal-title" data-rte-modal-title tabindex="-1">Insert Video</h3>
                <button type="button" class="rte-modal-close" data-editor="{{ $editorId }}" data-modal="video" aria-label="Close">&times;</button>
            </div>
            <div class="rte-modal-body">
                <input type="text" placeholder="Enter URL (YouTube, Vimeo, etc.)" class="rte-modal-input rte-video-url" value="https://">
            </div>
            <div class="rte-modal-footer">
                <button type="button" class="rte-btn-cancel" data-editor="{{ $editorId }}" data-modal="video">Cancel</button>
                <button type="button" class="rte-btn-submit rte-video-submit" data-editor="{{ $editorId }}">Insert Video</button>
            </div>
        </div>
    </div>

    {{-- Special Characters Modal --}}
    <div class="rte-modal rte-special-char-modal" id="{{ $editorId }}-special-char-modal" style="display: none;" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="{{ $editorId }}-special-char-modal-title">
        <div class="rte-modal-content">
            <div class="rte-modal-header">
                <h3 id="{{ $editorId }}-special-char-modal-title" data-rte-modal-title tabindex="-1">Special Characters</h3>
                <button type="button" class="rte-modal-close" data-editor="{{ $editorId }}" data-modal="specialChar" aria-label="Close">&times;</button>
            </div>
            <div class="rte-modal-body">
                <div class="rte-special-char-grid"></div>
            </div>
        </div>
    </div>
</div>

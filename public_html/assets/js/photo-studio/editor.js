/* exported setTool, rotateImage, flipImage, applyFilter, applyAllFilters, resetFilters, undo, redo, zoomIn, zoomOut, resetZoom, bgRemove, downloadImage, openPrintSheetModal, closePrintSheetModal, generatePrintSheet, deleteCurrentImage, toggleToolsPanel, preparePrintReady, fitToGuide, centerSubject, clearBackgroundLayer, setBackgroundColor, toggleGuides, applyCrop */

import { parseJsonResponse, getCsrfToken } from '../shared/utils.js';

function createBlobFromCanvas(canvas, type = 'image/png', quality = 0.92) {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (!blob) {
        reject(new Error('Failed to create image blob'));
        return;
      }
      resolve(blob);
    }, type, quality);
  });
}

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

class PhotoStudio {
  constructor() {
    this.config = this.readConfig();
    this.canvas = document.getElementById('photoCanvas');
    this.ctx = this.canvas.getContext('2d');
    this.container = document.getElementById('canvasContainer');
    this.placeholder = document.getElementById('uploadPlaceholder');
    this.app = document.getElementById('studioApp');
    this.scrollArea = document.getElementById('canvasScroll');
    this.modeBadge = document.getElementById('canvasModeBadge');
    this.toolHint = document.getElementById('toolHint');
    this.sessionMeta = document.getElementById('sessionMeta');
    this.imageInfo = document.getElementById('imageInfo');
    this.zoomLevel = document.getElementById('zoomLevel');
    this.printPreview = document.getElementById('printSheetPreview');
    this.cropHandleSize = 16;

    this.baseImage = null;
    this.foregroundCanvas = null;
    this.foregroundCtx = null;
    this.currentSourceUrl = null;
    this.remoteCutoutUrl = null;
    this.trayImages = [];
    this.activeImageIndex = -1;
    this.history = [];
    this.historyIndex = -1;
    this.maxHistory = 50;

    this.currentTool = 'select';
    this.isPointerDown = false;
    this.dragMode = null;
    this.pointerStart = null;
    this.lastPointer = null;
    this.cropStart = null;
    this.cropHandle = null;
    this.cropDragOrigin = null;
    this.shapeDraft = null;
    this.selectedOverlayId = null;

    this.state = this.createDefaultState();

    this.initializeUi();
    this.initializeEventListeners();
    this.loadSavedState();
    this.applyPreset(this.state.activePresetId, false);
    this.updateUndoRedoButtons();
    this.updateToolUi();
    this.updateStatus();
    this.renderTray();
  }

  readConfig() {
    try {
      const raw = document.getElementById('studioConfigData')?.textContent || '{}';
      return JSON.parse(raw);
    } catch {
      return {
        default_preset: 'bd_passport',
        presets: [],
        page_sizes: [],
        background_presets: [],
        default_print: {
          page_size: 'A4',
          orientation: 'portrait',
          layout: 'center',
          spacing_mm: 4,
        },
      };
    }
  }

  createDefaultState() {
    return {
      activePresetId: this.config.default_preset || 'bd_passport',
      brightness: 0,
      contrast: 0,
      saturation: 0,
      zoom: 1,
      guidesVisible: true,
      guideOpacity: 0.76,
      cropRect: null,
      background: {
        mode: 'color',
        color: '#ffffff',
        preset: 'white',
      },
      foreground: {
        x: 0,
        y: 0,
        scale: 1,
        rotation: 0,
        flipX: 1,
        flipY: 1,
      },
      overlays: [],
      print: {
        page_size: this.config.default_print?.page_size || 'A4',
        orientation: this.config.default_print?.orientation || 'portrait',
        layout: this.config.default_print?.layout || 'center',
        spacing_mm: this.config.default_print?.spacing_mm || 4,
      },
    };
  }

  getPresetById(presetId) {
    return this.config.presets.find((preset) => preset.id === presetId) || this.config.presets[0] || {
      id: 'custom',
      label: 'Custom',
      width_mm: 35,
      height_mm: 45,
      output_width: 413,
      output_height: 531,
      safe_area: { left: 0.1, right: 0.1, top: 0.1, bottom: 0.1, },
      head_box: { x: 0.24, y: 0.16, width: 0.52, height: 0.58, },
      background_default: '#ffffff',
      description: 'Custom preset',
    };
  }

  getActivePreset() {
    return this.getPresetById(this.state.activePresetId);
  }

  initializeUi() {
    this.populatePresetControls();
    this.populateBackgroundPresets();
    this.populatePageSizeOptions();
    this.syncControls();
  }

  initializeEventListeners() {
    const fileInput = document.getElementById('imageUploadInput');
    fileInput.addEventListener('change', (event) => {
      this.handleFiles(event.target.files);
      event.target.value = '';
    });

    const canvasArea = document.getElementById('canvasArea');
    canvasArea.addEventListener('dragover', (event) => {
      event.preventDefault();
    });
    canvasArea.addEventListener('drop', (event) => {
      event.preventDefault();
      this.handleFiles(event.dataTransfer.files);
    });

    this.canvas.addEventListener('mousedown', (event) => this.handlePointerDown(event));
    this.canvas.addEventListener('mousemove', (event) => this.handlePointerMove(event));
    this.canvas.addEventListener('mouseup', () => void this.handlePointerUp());
    this.canvas.addEventListener('mouseleave', () => void this.handlePointerUp());
    this.canvas.addEventListener('touchstart', (event) => this.handleTouchStart(event), { passive: false, });
    this.canvas.addEventListener('touchmove', (event) => this.handleTouchMove(event), { passive: false, });
    this.canvas.addEventListener('touchend', () => void this.handlePointerUp());

    document.addEventListener('keydown', (event) => this.handleKeyDown(event));

    document.getElementById('presetSelect').addEventListener('change', (event) => {
      this.applyPreset(event.target.value, true);
    });
    document.getElementById('brushSizeSlider').addEventListener('input', () => this.updateStatus());
    document.getElementById('brushColorPicker').addEventListener('input', () => this.updateStatus());
    document.getElementById('foregroundScaleSlider').addEventListener('input', (event) => {
      this.state.foreground.scale = clamp(parseInt(event.target.value, 10) / 100, 0.4, 2.2);
      this.render();
      this.persistState();
      this.updateStatus();
    });
    document.getElementById('guideOpacitySlider').addEventListener('input', (event) => {
      this.state.guideOpacity = clamp(parseInt(event.target.value, 10) / 100, 0.1, 1);
      this.render();
      this.persistState();
    });
    document.getElementById('backgroundColorPicker').addEventListener('input', (event) => {
      this.setBackgroundColor(event.target.value);
    });

    ['brightness', 'contrast', 'saturation',].forEach((prop) => {
      const slider = document.getElementById(`${prop}Slider`);
      slider.addEventListener('input', () => {
        this.state[prop] = parseInt(slider.value, 10);
        this.render();
        this.persistState();
      });
    });

    ['printPageSize', 'printOrientation', 'printLayout', 'printSpacing',].forEach((id) => {
      document.getElementById(id).addEventListener('input', () => {
        this.state.print.page_size = document.getElementById('printPageSize').value;
        this.state.print.orientation = document.getElementById('printOrientation').value;
        this.state.print.layout = document.getElementById('printLayout').value;
        this.state.print.spacing_mm = parseFloat(document.getElementById('printSpacing').value) || 4;
        this.persistState();
        this.renderPrintPreview();
      });
    });
  }

  populatePresetControls() {
    const select = document.getElementById('presetSelect');
    const chipGrid = document.getElementById('presetChipGrid');
    select.innerHTML = '';
    chipGrid.innerHTML = '';

    this.config.presets.forEach((preset) => {
      const option = document.createElement('option');
      option.value = preset.id;
      option.textContent = `${preset.label} (${preset.width_mm}x${preset.height_mm}mm)`;
      select.appendChild(option);

      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'studio-chip';
      chip.dataset.presetChip = preset.id;
      chip.innerHTML = `<span>${preset.label}</span><small>${preset.category} • ${preset.width_mm}x${preset.height_mm}mm</small>`;
      chip.addEventListener('click', () => this.applyPreset(preset.id, true));
      chipGrid.appendChild(chip);
    });
  }

  populateBackgroundPresets() {
    const grid = document.getElementById('backgroundPresetGrid');
    grid.innerHTML = '';
    this.config.background_presets.forEach((preset) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'studio-swatch';
      button.dataset.backgroundPreset = preset.label;
      if (preset.mode === 'gradient') {
        button.style.background = `linear-gradient(135deg, ${preset.value[0]}, ${preset.value[1]})`;
      } else {
        button.style.background = preset.value;
      }
      button.title = preset.label;
      button.addEventListener('click', () => this.setBackgroundPreset(preset));
      grid.appendChild(button);
    });
  }

  populatePageSizeOptions() {
    const pageSizeSelect = document.getElementById('printPageSize');
    pageSizeSelect.innerHTML = '';
    this.config.page_sizes.forEach((pageSize) => {
      const option = document.createElement('option');
      option.value = pageSize.label;
      option.textContent = pageSize.label;
      pageSizeSelect.appendChild(option);
    });
  }

  syncControls() {
    const preset = this.getActivePreset();
    document.getElementById('presetSelect').value = this.state.activePresetId;
    document.getElementById('brightnessSlider').value = this.state.brightness;
    document.getElementById('contrastSlider').value = this.state.contrast;
    document.getElementById('saturationSlider').value = this.state.saturation;
    document.getElementById('backgroundColorPicker').value = this.state.background.color || preset.background_default || '#ffffff';
    document.getElementById('foregroundScaleSlider').value = String(Math.round(this.state.foreground.scale * 100));
    document.getElementById('guideOpacitySlider').value = String(Math.round(this.state.guideOpacity * 100));
    document.getElementById('printPageSize').value = this.state.print.page_size;
    document.getElementById('printOrientation').value = this.state.print.orientation;
    document.getElementById('printLayout').value = this.state.print.layout;
    document.getElementById('printSpacing').value = String(this.state.print.spacing_mm);

    document.querySelectorAll('[data-preset-chip]').forEach((chip) => {
      chip.classList.toggle('active', chip.dataset.presetChip === this.state.activePresetId);
    });

    document.getElementById('guideToggleLabel').textContent = this.state.guidesVisible ? 'Hide Guides' : 'Show Guides';
    this.applyZoom();
  }

  async handleFiles(files) {
    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp',];

    for (const file of files) {
      if (!validTypes.includes(file.type)) {
        this.showToast(`Invalid file: ${file.name}`, 'error');
        continue;
      }
      if (file.size > 10 * 1024 * 1024) {
        this.showToast(`File too large: ${file.name}`, 'error');
        continue;
      }
      await this.uploadAndLoadImage(file);
    }
  }

  async uploadAndLoadImage(file) {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('csrf_token', getCsrfToken());

    try {
      this.showToast('Uploading portrait...', 'info');
      const response = await fetch('/studio/upload', {
        method: 'POST',
        body: formData,
      });
      const data = await parseJsonResponse(response);

      if (!response.ok || !data.success) {
        throw new Error(data.error || 'Upload failed');
      }

      const imageMeta = {
        filename: data.image.filename,
        url: data.image.url,
        original_name: data.image.original_name || file.name,
        variant: data.image.variant || 'upload',
      };

      this.trayImages.push(imageMeta);
      this.activeImageIndex = this.trayImages.length - 1;
      await this.loadImage(imageMeta.url);
      this.persistState();
      this.renderTray();
      this.showToast('Portrait uploaded', 'success');
    } catch (error) {
      this.showToast(error.message || 'Upload failed', 'error');
    }
  }

  async loadImage(src) {
    const image = await this.loadImageFromUrl(src);
    this.baseImage = image;
    this.currentSourceUrl = src;
    this.remoteCutoutUrl = null;
    this.prepareForegroundCanvas(image);
    this.resetCompositionForPreset();
    this.container.style.display = 'block';
    this.placeholder.style.display = 'none';
    this.render();
    this.saveToHistory();
    this.updateStatus();
    this.persistState();
  }

  prepareForegroundCanvas(image) {
    this.foregroundCanvas = document.createElement('canvas');
    this.foregroundCanvas.width = image.naturalWidth || image.width;
    this.foregroundCanvas.height = image.naturalHeight || image.height;
    this.foregroundCtx = this.foregroundCanvas.getContext('2d');
    this.foregroundCtx.clearRect(0, 0, this.foregroundCanvas.width, this.foregroundCanvas.height);
    this.foregroundCtx.drawImage(image, 0, 0);
  }

  resetCompositionForPreset() {
    const preset = this.getActivePreset();
    this.canvas.width = preset.output_width;
    this.canvas.height = preset.output_height;
    this.state.cropRect = null;
    this.state.foreground.rotation = 0;
    this.state.foreground.flipX = 1;
    this.state.foreground.flipY = 1;
    this.state.overlays = [];
    this.state.background.color = this.state.background.color || preset.background_default || '#ffffff';
    this.fitImageToCanvas();
    this.syncControls();
  }

  fitImageToCanvas() {
    if (!this.foregroundCanvas) {
      return;
    }

    const fitScale = Math.min(this.canvas.width / this.foregroundCanvas.width, this.canvas.height / this.foregroundCanvas.height);
    this.state.foreground.scale = clamp(fitScale, 0.4, 2.2);
    this.state.foreground.x = this.canvas.width / 2;
    this.state.foreground.y = this.canvas.height / 2;
    document.getElementById('foregroundScaleSlider').value = String(Math.round(this.state.foreground.scale * 100));
  }

  loadImageFromUrl(url) {
    return new Promise((resolve, reject) => {
      const image = new Image();
      image.onload = () => resolve(image);
      image.onerror = reject;
      image.src = url;
    });
  }

  applyPreset(presetId, saveHistory = true) {
    this.state.activePresetId = presetId;
    const preset = this.getActivePreset();
    document.getElementById('presetTitle').textContent = preset.label;
    document.getElementById('presetDescription').textContent = preset.description || 'Preset ready';
    if (this.baseImage) {
      this.resetCompositionForPreset();
    }
    this.syncControls();
    this.render();
    if (saveHistory && this.baseImage) {
      this.saveToHistory();
      this.persistState();
    }
    this.renderPrintPreview();
    this.updateStatus();
  }

  updateToolUi() {
    document.querySelectorAll('[data-tool-button]').forEach((button) => {
      button.classList.toggle('active', button.dataset.toolButton === this.currentTool);
    });

    const messages = {
      select: ['Select Tool', 'Drag the photo to align the face inside the guide box.',],
      crop: ['Crop Tool', 'Drag a crop box, then use the handles for cleaner framing.',],
      brush: ['Brush Tool', 'Retouch directly on the active portrait layer.',],
      eraser: ['Eraser Tool', 'Refine subject edges or remove stray pixels.',],
      text: ['Text Tool', 'Tap once to place a studio note or label.',],
      shape: ['Shape Tool', 'Drag to place a rectangle or circle overlay.',],
    };

    const [badge, hint,] = messages[this.currentTool] || messages.select;
    this.modeBadge.textContent = badge;
    this.toolHint.textContent = hint;
    this.canvas.style.cursor = this.currentTool === 'select' ? 'grab' : 'crosshair';
  }

  setTool(tool) {
    if (!this.baseImage && tool !== 'select') {
      this.showToast('Upload a portrait first', 'info');
      return;
    }

    this.currentTool = tool;
    if (tool !== 'crop') {
      this.state.cropRect = null;
    }
    this.updateToolUi();
    this.render();
  }

  getPointerPosition(event) {
    const rect = this.canvas.getBoundingClientRect();
    const scaleX = this.canvas.width / rect.width;
    const scaleY = this.canvas.height / rect.height;
    return {
      x: clamp((event.clientX - rect.left) * scaleX, 0, this.canvas.width),
      y: clamp((event.clientY - rect.top) * scaleY, 0, this.canvas.height),
    };
  }

  getImageBounds() {
    if (!this.foregroundCanvas) {
      return null;
    }

    const scale = this.state.foreground.scale;
    const width = this.foregroundCanvas.width * scale;
    const height = this.foregroundCanvas.height * scale;
    return {
      x: this.state.foreground.x - width / 2,
      y: this.state.foreground.y - height / 2,
      width,
      height,
    };
  }

  handlePointerDown(event) {
    if (!this.baseImage) {
      return;
    }

    const pos = this.getPointerPosition(event);
    this.pointerStart = pos;
    this.lastPointer = pos;
    this.isPointerDown = true;

    if (this.currentTool === 'crop') {
      const handle = this.getCropHandleAtPoint(pos);
      if (handle) {
        this.dragMode = 'crop-resize';
        this.cropHandle = handle;
        this.cropDragOrigin = { ...this.state.cropRect, };
        return;
      }

      if (this.state.cropRect && this.isPointInRect(pos, this.state.cropRect)) {
        this.dragMode = 'crop-move';
        this.cropDragOrigin = { ...this.state.cropRect, };
        return;
      }

      this.dragMode = 'crop-create';
      this.cropStart = pos;
      this.state.cropRect = { x: pos.x, y: pos.y, width: 0, height: 0, };
      this.render();
      return;
    }

    if (this.currentTool === 'select') {
      this.dragMode = 'move-subject';
      return;
    }

    if (this.currentTool === 'brush' || this.currentTool === 'eraser') {
      this.dragMode = this.currentTool;
      this.drawRetouchStroke(pos);
      return;
    }

    if (this.currentTool === 'shape') {
      this.dragMode = 'shape';
      this.shapeDraft = { x: pos.x, y: pos.y, width: 0, height: 0, };
      return;
    }

    if (this.currentTool === 'text') {
      this.placeTextOverlay(pos);
      this.isPointerDown = false;
    }
  }

  handlePointerMove(event) {
    if (!this.baseImage || !this.isPointerDown) {
      return;
    }

    const pos = this.getPointerPosition(event);
    const dx = pos.x - this.lastPointer.x;
    const dy = pos.y - this.lastPointer.y;

    if (this.dragMode === 'move-subject') {
      this.state.foreground.x += dx;
      this.state.foreground.y += dy;
      this.render();
    } else if (this.dragMode === 'crop-create' && this.cropStart) {
      this.state.cropRect = this.buildCropRect(this.cropStart, pos);
      this.render();
    } else if (this.dragMode === 'crop-move' && this.cropDragOrigin) {
      this.state.cropRect = this.clampRect({
        x: this.cropDragOrigin.x + (pos.x - this.pointerStart.x),
        y: this.cropDragOrigin.y + (pos.y - this.pointerStart.y),
        width: this.cropDragOrigin.width,
        height: this.cropDragOrigin.height,
      });
      this.render();
    } else if (this.dragMode === 'crop-resize' && this.cropHandle) {
      this.resizeCropRect(pos);
      this.render();
    } else if (this.dragMode === 'brush' || this.dragMode === 'eraser') {
      this.drawRetouchStroke(pos);
    } else if (this.dragMode === 'shape' && this.shapeDraft) {
      this.shapeDraft = {
        x: Math.min(this.pointerStart.x, pos.x),
        y: Math.min(this.pointerStart.y, pos.y),
        width: Math.abs(pos.x - this.pointerStart.x),
        height: Math.abs(pos.y - this.pointerStart.y),
      };
      this.render();
    }

    this.lastPointer = pos;
  }

  handlePointerUp() {
    if (!this.isPointerDown) {
      return;
    }

    if (this.dragMode === 'brush' || this.dragMode === 'eraser') {
      this.saveToHistory();
      this.persistState();
    }

    if (this.dragMode === 'shape' && this.shapeDraft && this.shapeDraft.width > 8 && this.shapeDraft.height > 8) {
      this.commitShapeOverlay();
    }

    this.isPointerDown = false;
    this.dragMode = null;
    this.cropHandle = null;
    this.cropDragOrigin = null;
    this.cropStart = null;
    this.pointerStart = null;
    this.lastPointer = null;
    this.shapeDraft = null;
    this.render();
    this.updateStatus();
  }

  handleTouchStart(event) {
    event.preventDefault();
    if (event.touches.length !== 1) {
      return;
    }

    const touch = event.touches[0];
    this.handlePointerDown({ clientX: touch.clientX, clientY: touch.clientY, });
  }

  handleTouchMove(event) {
    event.preventDefault();
    if (event.touches.length !== 1) {
      return;
    }

    const touch = event.touches[0];
    this.handlePointerMove({ clientX: touch.clientX, clientY: touch.clientY, });
  }

  handleKeyDown(event) {
    if (['INPUT', 'TEXTAREA', 'SELECT',].includes(event.target.tagName)) {
      return;
    }

    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
      event.preventDefault();
      if (event.shiftKey) {
        void this.redo();
      } else {
        void this.undo();
      }
      return;
    }

    const toolMap = {
      v: 'select',
      c: 'crop',
      b: 'brush',
      e: 'eraser',
      t: 'text',
      s: 'shape',
    };

    const tool = toolMap[event.key.toLowerCase()];
    if (tool) {
      this.setTool(tool);
    }

    if (event.key === 'Enter' && this.currentTool === 'crop' && this.state.cropRect) {
      event.preventDefault();
      void this.applyRectCrop();
    }
  }

  buildCropRect(start, end) {
    return this.clampRect({
      x: Math.min(start.x, end.x),
      y: Math.min(start.y, end.y),
      width: Math.abs(end.x - start.x),
      height: Math.abs(end.y - start.y),
    });
  }

  clampRect(rect) {
    const width = clamp(rect.width, 20, this.canvas.width);
    const height = clamp(rect.height, 20, this.canvas.height);
    return {
      x: clamp(rect.x, 0, this.canvas.width - width),
      y: clamp(rect.y, 0, this.canvas.height - height),
      width,
      height,
    };
  }

  getCropHandles() {
    if (!this.state.cropRect) {
      return [];
    }

    const { x, y, width, height, } = this.state.cropRect;
    return [
      { key: 'nw', x, y, },
      { key: 'ne', x: x + width, y, },
      { key: 'sw', x, y: y + height, },
      { key: 'se', x: x + width, y: y + height, },
    ];
  }

  getCropHandleAtPoint(pos) {
    return this.getCropHandles().find((handle) => Math.hypot(handle.x - pos.x, handle.y - pos.y) <= this.cropHandleSize);
  }

  resizeCropRect(pos) {
    const origin = this.cropDragOrigin;
    if (!origin || !this.cropHandle) {
      return;
    }

    const rect = { ...origin, };
    if (this.cropHandle.key.includes('n')) {
      const bottom = origin.y + origin.height;
      rect.y = clamp(pos.y, 0, bottom - 20);
      rect.height = bottom - rect.y;
    }
    if (this.cropHandle.key.includes('s')) {
      rect.height = clamp(pos.y - origin.y, 20, this.canvas.height - origin.y);
    }
    if (this.cropHandle.key.includes('w')) {
      const right = origin.x + origin.width;
      rect.x = clamp(pos.x, 0, right - 20);
      rect.width = right - rect.x;
    }
    if (this.cropHandle.key.includes('e')) {
      rect.width = clamp(pos.x - origin.x, 20, this.canvas.width - origin.x);
    }
    this.state.cropRect = this.clampRect(rect);
  }

  isPointInRect(pos, rect) {
    return pos.x >= rect.x && pos.x <= rect.x + rect.width && pos.y >= rect.y && pos.y <= rect.y + rect.height;
  }

  drawRetouchStroke(pos) {
    if (!this.foregroundCtx || !this.foregroundCanvas) {
      return;
    }

    const bounds = this.getImageBounds();
    if (!bounds) {
      return;
    }

    const brushSize = parseInt(document.getElementById('brushSizeSlider').value, 10) || 20;
    const imagePoint = this.canvasToImagePoint(pos, bounds);
    const previousPoint = this.lastPointer ? this.canvasToImagePoint(this.lastPointer, bounds) : imagePoint;

    this.foregroundCtx.save();
    this.foregroundCtx.lineCap = 'round';
    this.foregroundCtx.lineJoin = 'round';
    this.foregroundCtx.lineWidth = brushSize / Math.max(this.state.foreground.scale, 0.01);

    if (this.currentTool === 'eraser') {
      this.foregroundCtx.globalCompositeOperation = 'destination-out';
      this.foregroundCtx.strokeStyle = 'rgba(0,0,0,1)';
    } else {
      this.foregroundCtx.globalCompositeOperation = 'source-over';
      this.foregroundCtx.strokeStyle = document.getElementById('brushColorPicker').value;
    }

    this.foregroundCtx.beginPath();
    this.foregroundCtx.moveTo(previousPoint.x, previousPoint.y);
    this.foregroundCtx.lineTo(imagePoint.x, imagePoint.y);
    this.foregroundCtx.stroke();
    this.foregroundCtx.restore();
    this.render();
  }

  canvasToImagePoint(pos, bounds) {
    const normalizedX = (pos.x - bounds.x) / Math.max(bounds.width, 1);
    const normalizedY = (pos.y - bounds.y) / Math.max(bounds.height, 1);
    return {
      x: clamp(normalizedX * this.foregroundCanvas.width, 0, this.foregroundCanvas.width),
      y: clamp(normalizedY * this.foregroundCanvas.height, 0, this.foregroundCanvas.height),
    };
  }

  placeTextOverlay(pos) {
    const text = document.getElementById('textInputValue').value.trim() || 'ID PHOTO';
    const size = clamp(parseInt(document.getElementById('textSizeInput').value, 10) || 36, 16, 120);
    const color = document.getElementById('textColorPicker').value || '#ffffff';

    this.state.overlays.push({
      id: `overlay_${Date.now()}`,
      type: 'text',
      text,
      color,
      size,
      x: pos.x,
      y: pos.y,
    });
    this.saveToHistory();
    this.persistState();
    this.render();
    this.showToast('Text overlay added', 'success');
  }

  commitShapeOverlay() {
    this.state.overlays.push({
      id: `overlay_${Date.now()}`,
      type: 'shape',
      shape: document.getElementById('shapeTypeSelect').value,
      color: document.getElementById('shapeColorPicker').value,
      rect: { ...this.shapeDraft, },
    });
    this.saveToHistory();
    this.persistState();
    this.render();
    this.showToast('Shape overlay added', 'success');
  }

  createAdjustedForegroundCanvas() {
    const temp = document.createElement('canvas');
    temp.width = this.foregroundCanvas.width;
    temp.height = this.foregroundCanvas.height;
    const tempCtx = temp.getContext('2d');
    tempCtx.drawImage(this.foregroundCanvas, 0, 0);
    this.applyAdjustmentsToContext(tempCtx, temp.width, temp.height);
    return temp;
  }

  applyAdjustmentsToContext(ctx, width, height) {
    if (this.state.brightness === 0 && this.state.contrast === 0 && this.state.saturation === 0) {
      return;
    }

    const imageData = ctx.getImageData(0, 0, width, height);
    const { data, } = imageData;
    for (let index = 0; index < data.length; index += 4) {
      let r = data[index];
      let g = data[index + 1];
      let b = data[index + 2];

      if (this.state.brightness !== 0) {
        const adjust = this.state.brightness * 2.55;
        r += adjust;
        g += adjust;
        b += adjust;
      }

      if (this.state.contrast !== 0) {
        const factor = (259 * (this.state.contrast + 255)) / (255 * (259 - this.state.contrast));
        r = factor * (r - 128) + 128;
        g = factor * (g - 128) + 128;
        b = factor * (b - 128) + 128;
      }

      if (this.state.saturation !== 0) {
        const gray = 0.2989 * r + 0.587 * g + 0.114 * b;
        const satFactor = 1 + this.state.saturation / 100;
        r = gray + satFactor * (r - gray);
        g = gray + satFactor * (g - gray);
        b = gray + satFactor * (b - gray);
      }

      data[index] = clamp(Math.round(r), 0, 255);
      data[index + 1] = clamp(Math.round(g), 0, 255);
      data[index + 2] = clamp(Math.round(b), 0, 255);
    }
    ctx.putImageData(imageData, 0, 0);
  }

  drawBackground() {
    const width = this.canvas.width;
    const height = this.canvas.height;
    if (this.state.background.mode === 'transparent') {
      this.ctx.clearRect(0, 0, width, height);
      return;
    }

    if (this.state.background.mode === 'gradient' && Array.isArray(this.state.background.gradient)) {
      const gradient = this.ctx.createLinearGradient(0, 0, width, height);
      gradient.addColorStop(0, this.state.background.gradient[0]);
      gradient.addColorStop(1, this.state.background.gradient[1]);
      this.ctx.fillStyle = gradient;
    } else {
      this.ctx.fillStyle = this.state.background.color || '#ffffff';
    }
    this.ctx.fillRect(0, 0, width, height);
  }

  drawForeground() {
    if (!this.foregroundCanvas) {
      return;
    }

    const renderCanvas = this.createAdjustedForegroundCanvas();
    const bounds = this.getImageBounds();

    this.ctx.save();
    this.ctx.translate(this.state.foreground.x, this.state.foreground.y);
    this.ctx.rotate((this.state.foreground.rotation * Math.PI) / 180);
    this.ctx.scale(this.state.foreground.flipX * this.state.foreground.scale, this.state.foreground.flipY * this.state.foreground.scale);
    this.ctx.drawImage(renderCanvas, -this.foregroundCanvas.width / 2, -this.foregroundCanvas.height / 2);
    this.ctx.restore();

    if (this.currentTool === 'select' && bounds) {
      this.ctx.save();
      this.ctx.strokeStyle = 'rgba(14, 165, 233, 0.88)';
      this.ctx.lineWidth = 2;
      this.ctx.setLineDash([8, 6,]);
      this.ctx.strokeRect(bounds.x, bounds.y, bounds.width, bounds.height);
      this.ctx.restore();
    }
  }

  drawGuides() {
    if (!this.state.guidesVisible) {
      return;
    }

    const preset = this.getActivePreset();
    const safe = preset.safe_area || { left: 0.1, right: 0.1, top: 0.1, bottom: 0.1, };
    const headBox = preset.head_box || { x: 0.22, y: 0.16, width: 0.56, height: 0.6, };
    const alpha = this.state.guideOpacity;

    this.ctx.save();
    this.ctx.lineWidth = 1.5;
    this.ctx.strokeStyle = `rgba(34, 197, 94, ${alpha})`;
    this.ctx.setLineDash([10, 6,]);
    this.ctx.strokeRect(
      this.canvas.width * safe.left,
      this.canvas.height * safe.top,
      this.canvas.width * (1 - safe.left - safe.right),
      this.canvas.height * (1 - safe.top - safe.bottom)
    );

    this.ctx.strokeStyle = `rgba(249, 115, 22, ${alpha})`;
    this.ctx.strokeRect(
      this.canvas.width * headBox.x,
      this.canvas.height * headBox.y,
      this.canvas.width * headBox.width,
      this.canvas.height * headBox.height
    );

    this.ctx.strokeStyle = `rgba(255,255,255,${alpha * 0.72})`;
    this.ctx.beginPath();
    this.ctx.moveTo(this.canvas.width / 2, 0);
    this.ctx.lineTo(this.canvas.width / 2, this.canvas.height);
    this.ctx.moveTo(0, this.canvas.height / 2);
    this.ctx.lineTo(this.canvas.width, this.canvas.height / 2);
    this.ctx.stroke();
    this.ctx.restore();
  }

  drawCropOverlay() {
    if (!this.state.cropRect) {
      return;
    }

    const { x, y, width, height, } = this.state.cropRect;
    this.ctx.save();
    this.ctx.fillStyle = 'rgba(2, 6, 23, 0.48)';
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.clearRect(x, y, width, height);
    this.ctx.strokeStyle = '#f97316';
    this.ctx.lineWidth = 2.5;
    this.ctx.setLineDash([9, 6,]);
    this.ctx.strokeRect(x, y, width, height);
    this.ctx.setLineDash([]);
    this.getCropHandles().forEach((handle) => {
      this.ctx.fillStyle = '#ffffff';
      this.ctx.beginPath();
      this.ctx.arc(handle.x, handle.y, 6, 0, Math.PI * 2);
      this.ctx.fill();
      this.ctx.strokeStyle = '#0ea5e9';
      this.ctx.lineWidth = 2;
      this.ctx.stroke();
    });
    this.ctx.restore();
  }

  drawOverlays() {
    this.state.overlays.forEach((overlay) => {
      if (overlay.type === 'text') {
        this.ctx.save();
        this.ctx.font = `700 ${overlay.size}px "Plus Jakarta Sans", sans-serif`;
        this.ctx.textBaseline = 'top';
        this.ctx.strokeStyle = 'rgba(2, 6, 23, 0.55)';
        this.ctx.lineWidth = Math.max(2, overlay.size * 0.12);
        this.ctx.strokeText(overlay.text, overlay.x, overlay.y);
        this.ctx.fillStyle = overlay.color;
        this.ctx.fillText(overlay.text, overlay.x, overlay.y);
        this.ctx.restore();
      }

      if (overlay.type === 'shape') {
        const { rect, } = overlay;
        this.ctx.save();
        this.ctx.strokeStyle = overlay.color;
        this.ctx.fillStyle = `${overlay.color}33`;
        this.ctx.lineWidth = 4;
        if (overlay.shape === 'circle') {
          this.ctx.beginPath();
          this.ctx.ellipse(rect.x + rect.width / 2, rect.y + rect.height / 2, rect.width / 2, rect.height / 2, 0, 0, Math.PI * 2);
          this.ctx.fill();
          this.ctx.stroke();
        } else {
          this.ctx.fillRect(rect.x, rect.y, rect.width, rect.height);
          this.ctx.strokeRect(rect.x, rect.y, rect.width, rect.height);
        }
        this.ctx.restore();
      }
    });

    if (this.shapeDraft) {
      this.ctx.save();
      this.ctx.strokeStyle = '#38bdf8';
      this.ctx.lineWidth = 3;
      this.ctx.setLineDash([8, 5,]);
      this.ctx.strokeRect(this.shapeDraft.x, this.shapeDraft.y, this.shapeDraft.width, this.shapeDraft.height);
      this.ctx.restore();
    }
  }

  render() {
    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this.drawBackground();
    this.drawForeground();
    this.drawGuides();
    this.drawOverlays();
    if (this.currentTool === 'crop' && this.state.cropRect) {
      this.drawCropOverlay();
    }
  }

  saveToHistory() {
    if (!this.foregroundCanvas) {
      return;
    }

    const snapshot = {
      sourceUrl: this.currentSourceUrl,
      foreground: this.foregroundCanvas.toDataURL('image/png'),
      trayImages: JSON.parse(JSON.stringify(this.trayImages)),
      activeImageIndex: this.activeImageIndex,
      state: JSON.parse(JSON.stringify(this.state)),
    };

    this.history = this.history.slice(0, this.historyIndex + 1);
    this.history.push(snapshot);
    if (this.history.length > this.maxHistory) {
      this.history.shift();
    }
    this.historyIndex = this.history.length - 1;
    this.updateUndoRedoButtons();
  }

  async restoreSnapshot(snapshot) {
    if (!snapshot) {
      return;
    }

    this.trayImages = Array.isArray(snapshot.trayImages) ? snapshot.trayImages : [];
    this.activeImageIndex = snapshot.activeImageIndex ?? -1;
    this.state = snapshot.state || this.createDefaultState();
    this.syncControls();

    if (snapshot.sourceUrl) {
      this.currentSourceUrl = snapshot.sourceUrl;
      this.baseImage = await this.loadImageFromUrl(snapshot.sourceUrl);
    }

    if (snapshot.foreground) {
      const foregroundImage = await this.loadImageFromUrl(snapshot.foreground);
      this.prepareForegroundCanvas(foregroundImage);
    }

    const preset = this.getActivePreset();
    this.canvas.width = preset.output_width;
    this.canvas.height = preset.output_height;
    this.container.style.display = 'block';
    this.placeholder.style.display = 'none';
    this.renderTray();
    this.render();
    this.updateStatus();
    this.persistState();
  }

  async undo() {
    if (this.historyIndex <= 0) {
      return;
    }
    this.historyIndex -= 1;
    await this.restoreSnapshot(this.history[this.historyIndex]);
    this.updateUndoRedoButtons();
  }

  async redo() {
    if (this.historyIndex >= this.history.length - 1) {
      return;
    }
    this.historyIndex += 1;
    await this.restoreSnapshot(this.history[this.historyIndex]);
    this.updateUndoRedoButtons();
  }

  updateUndoRedoButtons() {
    document.getElementById('undoBtn').disabled = this.historyIndex <= 0;
    document.getElementById('redoBtn').disabled = this.historyIndex >= this.history.length - 1;
  }

  zoomIn() {
    this.state.zoom = clamp(this.state.zoom * 1.15, 0.2, 5);
    this.applyZoom();
    this.persistState();
  }

  zoomOut() {
    this.state.zoom = clamp(this.state.zoom / 1.15, 0.2, 5);
    this.applyZoom();
    this.persistState();
  }

  resetZoom() {
    this.state.zoom = 1;
    this.applyZoom();
    this.persistState();
  }

  applyZoom() {
    this.container.style.transform = `scale(${this.state.zoom})`;
    this.zoomLevel.textContent = `${Math.round(this.state.zoom * 100)}%`;
  }

  async applyRectCrop() {
    if (!this.state.cropRect || !this.foregroundCanvas) {
      return;
    }

    const exportCanvas = this.createCompositeCanvas();
    const { x, y, width, height, } = this.state.cropRect;
    const cropped = document.createElement('canvas');
    cropped.width = width;
    cropped.height = height;
    cropped.getContext('2d').drawImage(exportCanvas, x, y, width, height, 0, 0, width, height);

    const croppedImage = await this.loadImageFromUrl(cropped.toDataURL('image/png'));
    this.prepareForegroundCanvas(croppedImage);
    this.canvas.width = width;
    this.canvas.height = height;
    this.state.cropRect = null;
    this.fitImageToCanvas();
    this.saveToHistory();
    this.persistState();
    this.render();
    this.showToast('Crop applied', 'success');
  }

  async applyAllFilters() {
    if (!this.foregroundCanvas) {
      this.showToast('No image to edit', 'error');
      return;
    }

    const adjusted = this.createAdjustedForegroundCanvas();
    const adjustedImage = await this.loadImageFromUrl(adjusted.toDataURL('image/png'));
    this.prepareForegroundCanvas(adjustedImage);
    this.state.brightness = 0;
    this.state.contrast = 0;
    this.state.saturation = 0;
    this.syncControls();
    this.saveToHistory();
    this.persistState();
    this.render();
    this.showToast('Preview applied to portrait', 'success');
  }

  applyFilter() {
    this.render();
  }

  resetFilters() {
    this.state.brightness = 0;
    this.state.contrast = 0;
    this.state.saturation = 0;
    this.syncControls();
    this.render();
    this.persistState();
    this.showToast('Adjustment preview reset', 'info');
  }

  async rotateImage(angle = 90) {
    if (!this.foregroundCanvas) {
      return;
    }

    const radians = (angle * Math.PI) / 180;
    const swap = Math.abs(angle) % 180 === 90;
    const output = document.createElement('canvas');
    output.width = swap ? this.foregroundCanvas.height : this.foregroundCanvas.width;
    output.height = swap ? this.foregroundCanvas.width : this.foregroundCanvas.height;
    const outputCtx = output.getContext('2d');

    outputCtx.translate(output.width / 2, output.height / 2);
    outputCtx.rotate(radians);
    outputCtx.drawImage(this.foregroundCanvas, -this.foregroundCanvas.width / 2, -this.foregroundCanvas.height / 2);

    const rotatedImage = await this.loadImageFromUrl(output.toDataURL('image/png'));
    this.prepareForegroundCanvas(rotatedImage);
    this.fitImageToCanvas();
    this.saveToHistory();
    this.persistState();
    this.render();
    this.showToast(`Rotated ${angle} degrees`, 'success');
  }

  async flipImage(direction) {
    if (!this.foregroundCanvas) {
      return;
    }

    const output = document.createElement('canvas');
    output.width = this.foregroundCanvas.width;
    output.height = this.foregroundCanvas.height;
    const outputCtx = output.getContext('2d');

    outputCtx.save();
    if (direction === 'horizontal') {
      outputCtx.translate(output.width, 0);
      outputCtx.scale(-1, 1);
    } else {
      outputCtx.translate(0, output.height);
      outputCtx.scale(1, -1);
    }
    outputCtx.drawImage(this.foregroundCanvas, 0, 0);
    outputCtx.restore();

    const flippedImage = await this.loadImageFromUrl(output.toDataURL('image/png'));
    this.prepareForegroundCanvas(flippedImage);
    this.saveToHistory();
    this.persistState();
    this.render();
    this.showToast('Flip applied', 'success');
  }

  async bgRemove() {
    if (!this.foregroundCanvas) {
      this.showToast('No image to isolate', 'error');
      return;
    }

    try {
      this.showToast('Removing background...', 'info');
      const blob = await createBlobFromCanvas(this.foregroundCanvas, 'image/png');
      const formData = new FormData();
      formData.append('image', blob, 'foreground.png');
      formData.append('csrf_token', getCsrfToken());

      const response = await fetch('/studio/remove-background', {
        method: 'POST',
        body: formData,
      });
      const result = await parseJsonResponse(response);

      if (!response.ok || !result.success) {
        throw new Error(result.error || 'Background removal failed');
      }

      const cutout = await this.loadImageFromUrl(result.cutout_url);
      this.remoteCutoutUrl = result.cutout_url;
      this.prepareForegroundCanvas(cutout);
      this.state.background.mode = 'color';
      this.state.background.color = '#ffffff';
      this.saveToHistory();
      this.persistState();
      this.render();
      this.showToast(`Cutout ready with ${result.processing?.engine || 'studio engine'}`, 'success');
    } catch (error) {
      this.showToast(error.message || 'Background removal failed', 'error');
    }
  }

  setBackgroundPreset(preset) {
    if (preset.mode === 'gradient') {
      this.state.background.mode = 'gradient';
      this.state.background.gradient = preset.value;
    } else {
      this.state.background.mode = 'color';
      this.state.background.color = preset.value;
      this.state.background.gradient = null;
    }
    this.state.background.preset = preset.label;
    document.querySelectorAll('[data-background-preset]').forEach((swatch) => {
      swatch.classList.toggle('active', swatch.dataset.backgroundPreset === preset.label);
    });
    this.render();
    this.persistState();
  }

  setBackgroundColor(color) {
    this.state.background.mode = 'color';
    this.state.background.color = color;
    this.state.background.gradient = null;
    this.render();
    this.persistState();
  }

  clearBackgroundLayer() {
    this.state.background.mode = 'transparent';
    this.state.background.gradient = null;
    this.render();
    this.persistState();
  }

  toggleGuides() {
    this.state.guidesVisible = !this.state.guidesVisible;
    this.syncControls();
    this.render();
    this.persistState();
  }

  fitToGuide() {
    const preset = this.getActivePreset();
    const headBox = preset.head_box;
    if (!headBox || !this.foregroundCanvas) {
      return;
    }

    const targetWidth = this.canvas.width * headBox.width;
    this.state.foreground.scale = clamp(targetWidth / this.foregroundCanvas.width, 0.4, 2.2);
    this.state.foreground.x = this.canvas.width / 2;
    this.state.foreground.y = this.canvas.height * (headBox.y + (headBox.height / 2));
    this.syncControls();
    this.render();
    this.persistState();
    this.showToast('Subject fitted to guide box', 'success');
  }

  centerSubject() {
    this.state.foreground.x = this.canvas.width / 2;
    this.state.foreground.y = this.canvas.height / 2;
    this.render();
    this.persistState();
    this.showToast('Subject centered', 'success');
  }

  async preparePrintReady() {
    if (!this.foregroundCanvas) {
      this.showToast('No active portrait to prepare', 'error');
      return;
    }

    this.fitToGuide();
    if (this.state.background.mode === 'transparent') {
      this.state.background.mode = 'color';
      this.state.background.color = '#ffffff';
    }
    this.render();
    await this.persistActiveImageToTray('print_ready');
    this.showToast('Print-ready version prepared', 'success');
  }

  createCompositeCanvas() {
    const output = document.createElement('canvas');
    output.width = this.canvas.width;
    output.height = this.canvas.height;
    const outputCtx = output.getContext('2d');

    if (this.state.background.mode === 'transparent') {
      outputCtx.clearRect(0, 0, output.width, output.height);
    } else if (this.state.background.mode === 'gradient' && Array.isArray(this.state.background.gradient)) {
      const gradient = outputCtx.createLinearGradient(0, 0, output.width, output.height);
      gradient.addColorStop(0, this.state.background.gradient[0]);
      gradient.addColorStop(1, this.state.background.gradient[1]);
      outputCtx.fillStyle = gradient;
      outputCtx.fillRect(0, 0, output.width, output.height);
    } else {
      outputCtx.fillStyle = this.state.background.color || '#ffffff';
      outputCtx.fillRect(0, 0, output.width, output.height);
    }

    if (this.foregroundCanvas) {
      const renderCanvas = this.createAdjustedForegroundCanvas();
      outputCtx.save();
      outputCtx.translate(this.state.foreground.x, this.state.foreground.y);
      outputCtx.rotate((this.state.foreground.rotation * Math.PI) / 180);
      outputCtx.scale(this.state.foreground.flipX * this.state.foreground.scale, this.state.foreground.flipY * this.state.foreground.scale);
      outputCtx.drawImage(renderCanvas, -this.foregroundCanvas.width / 2, -this.foregroundCanvas.height / 2);
      outputCtx.restore();
    }

    this.state.overlays.forEach((overlay) => {
      if (overlay.type === 'text') {
        outputCtx.font = `700 ${overlay.size}px "Plus Jakarta Sans", sans-serif`;
        outputCtx.textBaseline = 'top';
        outputCtx.strokeStyle = 'rgba(2, 6, 23, 0.55)';
        outputCtx.lineWidth = Math.max(2, overlay.size * 0.12);
        outputCtx.strokeText(overlay.text, overlay.x, overlay.y);
        outputCtx.fillStyle = overlay.color;
        outputCtx.fillText(overlay.text, overlay.x, overlay.y);
      } else if (overlay.type === 'shape') {
        const { rect, } = overlay;
        outputCtx.strokeStyle = overlay.color;
        outputCtx.fillStyle = `${overlay.color}33`;
        outputCtx.lineWidth = 4;
        if (overlay.shape === 'circle') {
          outputCtx.beginPath();
          outputCtx.ellipse(rect.x + rect.width / 2, rect.y + rect.height / 2, rect.width / 2, rect.height / 2, 0, 0, Math.PI * 2);
          outputCtx.fill();
          outputCtx.stroke();
        } else {
          outputCtx.fillRect(rect.x, rect.y, rect.width, rect.height);
          outputCtx.strokeRect(rect.x, rect.y, rect.width, rect.height);
        }
      }
    });

    return output;
  }

  async persistActiveImageToTray(variant = 'edit') {
    if (this.activeImageIndex < 0) {
      return null;
    }

    const blob = await createBlobFromCanvas(this.createCompositeCanvas(), 'image/png');
    const formData = new FormData();
    formData.append('image', blob, `${variant}.png`);
    formData.append('variant', variant);
    formData.append('csrf_token', getCsrfToken());

    const response = await fetch('/studio/save', {
      method: 'POST',
      body: formData,
    });
    const result = await parseJsonResponse(response);
    if (!response.ok || !result.success) {
      throw new Error(result.error || 'Failed to save composition');
    }

    const active = this.trayImages[this.activeImageIndex];
    this.trayImages[this.activeImageIndex] = {
      ...active,
      filename: result.image.filename,
      url: result.image.url,
      variant: result.image.variant,
    };
    this.persistState();
    this.renderTray();
    return result.image.url;
  }

  async saveImage(format = 'png') {
    if (!this.foregroundCanvas) {
      this.showToast('No active portrait to export', 'error');
      return null;
    }

    const type = format === 'jpg' ? 'image/jpeg' : 'image/png';
    const blob = await createBlobFromCanvas(this.createCompositeCanvas(), type);
    try {
      const formData = new FormData();
      formData.append('image', blob, `studio-export.${format}`);
      formData.append('variant', 'final');
      formData.append('csrf_token', getCsrfToken());

      const response = await fetch('/studio/save', {
        method: 'POST',
        body: formData,
      });
      const result = await parseJsonResponse(response);
      if (!response.ok || !result.success) {
        throw new Error(result.error || 'Save failed');
      }

      const link = document.createElement('a');
      link.href = result.image.url;
      link.download = `brox-studio-${this.state.activePresetId}.${format}`;
      link.click();
      this.showToast('Export downloaded', 'success');
      return result.image.url;
    } catch (error) {
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = `brox-studio-${this.state.activePresetId}.${format}`;
      link.click();
      this.showToast(error.message || 'Export saved locally', 'info');
      return null;
    }
  }

  async loadFromTray(index) {
    const imageMeta = this.trayImages[index];
    if (!imageMeta) {
      return;
    }

    this.activeImageIndex = index;
    await this.loadImage(imageMeta.url);
    this.renderTray();
    this.renderPrintPreview();
  }

  renderTray() {
    const tray = document.getElementById('imageTray');
    tray.innerHTML = '';

    if (this.trayImages.length === 0) {
      tray.innerHTML = '<p class="studio-helper">No portraits uploaded yet.</p>';
      return;
    }

    this.trayImages.forEach((image, index) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = `tray-item ${index === this.activeImageIndex ? 'active' : ''}`;
      item.innerHTML = `<img src="${image.url}" alt="${image.original_name || `Image ${index + 1}`}">`;
      item.addEventListener('click', () => void this.loadFromTray(index));

      const removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.className = 'tray-item-remove';
      removeButton.innerHTML = '<i class="lucide lucide-x" style="width:1rem;height:1rem;"></i>';
      removeButton.addEventListener('click', (event) => {
        event.stopPropagation();
        void this.deleteTrayImage(index);
      });

      item.appendChild(removeButton);
      tray.appendChild(item);
    });
  }

  async deleteTrayImage(index) {
    const imageMeta = this.trayImages[index];
    if (!imageMeta || !(await window.showConfirm('Delete this image from the tray?'))) {
      return;
    }

    try {
      await fetch('/studio/image', {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify({ filename: imageMeta.filename, }),
      });
    } catch (_error) {
      // Ignore endpoint cleanup failures and continue with local state.
    }

    this.trayImages.splice(index, 1);
    if (this.trayImages.length === 0) {
      this.activeImageIndex = -1;
      this.baseImage = null;
      this.foregroundCanvas = null;
      this.foregroundCtx = null;
      this.container.style.display = 'none';
      this.placeholder.style.display = 'flex';
      this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
      this.history = [];
      this.historyIndex = -1;
      this.updateUndoRedoButtons();
    } else {
      this.activeImageIndex = Math.max(0, Math.min(index, this.trayImages.length - 1));
      await this.loadFromTray(this.activeImageIndex);
    }

    this.persistState();
    this.renderTray();
    this.renderPrintPreview();
    this.showToast('Tray image deleted', 'success');
  }

  openPrintSheetModal() {
    if (this.trayImages.length === 0) {
      this.showToast('Add portraits before printing', 'info');
      return;
    }

    const modal = document.getElementById('printSheetModal');
    const list = document.getElementById('printSheetImageList');
    list.innerHTML = '';

    this.trayImages.forEach((image, index) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = `tray-item ${index === this.activeImageIndex ? 'active selected' : 'selected'}`;
      item.dataset.printImage = 'true';
      item.dataset.url = image.url;
      item.innerHTML = `<img src="${image.url}" alt="${image.original_name || `Image ${index + 1}`}">`;
      item.addEventListener('click', () => {
        item.classList.toggle('selected');
        this.renderPrintPreview();
      });
      list.appendChild(item);
    });

    modal.style.display = 'flex';
    this.renderPrintPreview();
  }

  closePrintSheetModal() {
    document.getElementById('printSheetModal').style.display = 'none';
  }

  renderPrintPreview() {
    if (!this.printPreview) {
      return;
    }

    const preset = this.getActivePreset();
    const selected = Array.from(document.querySelectorAll('[data-print-image].selected'));
    const count = Math.max(1, selected.length || this.trayImages.length || 1);
    this.printPreview.innerHTML = '';

    const boxWidth = Math.max(48, preset.width_mm * 2.1);
    const boxHeight = Math.max(58, preset.height_mm * 2.1);
    const gap = clamp((parseFloat(document.getElementById('printSpacing').value) || 4) * 2.4, 4, 24);
    const maxCols = Math.max(1, Math.floor((this.printPreview.clientWidth || 620) / (boxWidth + gap)));
    const previewImages = selected.length > 0 ? selected : this.trayImages.map((image) => ({ dataset: { url: image.url, }, }));

    previewImages.slice(0, Math.min(count, 12)).forEach((item, index) => {
      const col = index % maxCols;
      const row = Math.floor(index / maxCols);
      const cell = document.createElement('div');
      cell.className = 'studio-preview-photo';
      cell.style.width = `${boxWidth}px`;
      cell.style.height = `${boxHeight}px`;
      cell.style.left = `${12 + col * (boxWidth + gap)}px`;
      cell.style.top = `${12 + row * (boxHeight + gap)}px`;
      cell.style.backgroundImage = `url('${item.dataset.url}')`;
      this.printPreview.appendChild(cell);
    });
  }

  async generatePrintSheet() {
    try {
      if (this.activeImageIndex >= 0) {
        await this.persistActiveImageToTray('print_ready');
      }

      const selectedImages = Array.from(document.querySelectorAll('[data-print-image].selected'))
        .map((item) => item.dataset.url)
        .filter(Boolean);

      if (selectedImages.length === 0) {
        this.showToast('Select at least one portrait', 'error');
        return;
      }

      const response = await fetch('/studio/print-sheet', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify({
          images: selectedImages,
          page_size: document.getElementById('printPageSize').value,
          orientation: document.getElementById('printOrientation').value,
          layout: document.getElementById('printLayout').value,
          spacing_mm: parseFloat(document.getElementById('printSpacing').value) || 4,
          photo_preset: this.state.activePresetId,
          csrf_token: getCsrfToken(),
        }),
      });
      const result = await parseJsonResponse(response);
      if (!response.ok || !result.success) {
        throw new Error(result.error || 'Print sheet generation failed');
      }

      window.open(result.download_url, '_blank', 'noopener');
      this.closePrintSheetModal();
      this.showToast('Print sheet PDF generated', 'success');
    } catch (error) {
      this.showToast(error.message || 'Print generation failed', 'error');
    }
  }

  updateStatus() {
    const preset = this.getActivePreset();
    this.imageInfo.textContent = this.baseImage
      ? `${this.canvas.width} x ${this.canvas.height}px • ${preset.width_mm}x${preset.height_mm}mm`
      : 'No image loaded';

    const toolName = this.currentTool.charAt(0).toUpperCase() + this.currentTool.slice(1);
    const scale = `${Math.round(this.state.foreground.scale * 100)}%`;
    this.sessionMeta.textContent = this.baseImage
      ? `${toolName} • ${preset.label} • Subject scale ${scale}`
      : 'Preset ready for export';
  }

  toggleToolsPanel(forceOpen) {
    const open = typeof forceOpen === 'boolean' ? forceOpen : !this.app.classList.contains('sidebar-open');
    this.app.classList.toggle('sidebar-open', open);
  }

  persistState() {
    const payload = {
      version: 3,
      trayImages: this.trayImages,
      activeImageIndex: this.activeImageIndex,
      state: this.state,
      sourceUrl: this.currentSourceUrl,
      foregroundDataUrl: this.foregroundCanvas ? this.foregroundCanvas.toDataURL('image/png') : null,
    };
    window.localStorage.setItem('broxstudio_session', JSON.stringify(payload));
  }

  loadSavedState() {
    const saved = window.localStorage.getItem('broxstudio_session');
    if (!saved) {
      return;
    }

    try {
      const payload = JSON.parse(saved);
      this.trayImages = Array.isArray(payload.trayImages) ? payload.trayImages : [];
      this.activeImageIndex = typeof payload.activeImageIndex === 'number' ? payload.activeImageIndex : -1;
      this.state = payload.state ? {
        ...this.createDefaultState(),
        ...payload.state,
        background: {
          ...this.createDefaultState().background,
          ...(payload.state.background || {}),
        },
        foreground: {
          ...this.createDefaultState().foreground,
          ...(payload.state.foreground || {}),
        },
        print: {
          ...this.createDefaultState().print,
          ...(payload.state.print || {}),
        },
      } : this.createDefaultState();
      this.currentSourceUrl = payload.sourceUrl || null;
      this.syncControls();

      if (payload.foregroundDataUrl) {
        void this.restoreSavedForeground(payload.foregroundDataUrl);
      }
    } catch {
      window.localStorage.removeItem('broxstudio_session');
    }
  }

  async restoreSavedForeground(foregroundDataUrl) {
    try {
      const restored = await this.loadImageFromUrl(foregroundDataUrl);
      this.baseImage = restored;
      this.prepareForegroundCanvas(restored);
      const preset = this.getActivePreset();
      this.canvas.width = preset.output_width;
      this.canvas.height = preset.output_height;
      this.container.style.display = 'block';
      this.placeholder.style.display = 'none';
      this.render();
      this.renderTray();
      this.renderPrintPreview();
      this.updateStatus();
    } catch {
      // Ignore invalid saved foreground data.
    }
  }

  showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="lucide lucide-${type === 'success' ? 'check-circle' : type === 'error' ? 'alert-circle' : 'info'} mr-2" style="width:1rem;height:1rem;"></i> ${message}`;
    container.appendChild(toast);
    window.setTimeout(() => toast.remove(), 3200);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  window.photoStudio = new PhotoStudio();
  window.studioInstance = window.photoStudio;

  window.setTool = (tool) => window.studioInstance.setTool(tool);
  window.rotateImage = () => void window.studioInstance.rotateImage(90);
  window.flipImage = (direction) => void window.studioInstance.flipImage(direction);
  window.applyFilter = () => window.studioInstance.applyFilter();
  window.applyAllFilters = () => void window.studioInstance.applyAllFilters();
  window.resetFilters = () => window.studioInstance.resetFilters();
  window.undo = () => void window.studioInstance.undo();
  window.redo = () => void window.studioInstance.redo();
  window.zoomIn = () => window.studioInstance.zoomIn();
  window.zoomOut = () => window.studioInstance.zoomOut();
  window.resetZoom = () => window.studioInstance.resetZoom();
  window.bgRemove = () => void window.studioInstance.bgRemove();
  window.downloadImage = () => void window.studioInstance.saveImage('png');
  window.openPrintSheetModal = () => window.studioInstance.openPrintSheetModal();
  window.closePrintSheetModal = () => window.studioInstance.closePrintSheetModal();
  window.generatePrintSheet = () => void window.studioInstance.generatePrintSheet();
  window.deleteCurrentImage = () => {
    if (window.studioInstance.activeImageIndex >= 0) {
      void window.studioInstance.deleteTrayImage(window.studioInstance.activeImageIndex);
    }
  };
  window.toggleToolsPanel = (forceOpen) => window.studioInstance.toggleToolsPanel(forceOpen);
  window.preparePrintReady = () => void window.studioInstance.preparePrintReady();
  window.fitToGuide = () => window.studioInstance.fitToGuide();
  window.centerSubject = () => window.studioInstance.centerSubject();
  window.clearBackgroundLayer = () => window.studioInstance.clearBackgroundLayer();
  window.setBackgroundColor = (color) => window.studioInstance.setBackgroundColor(color);
  window.toggleGuides = () => window.studioInstance.toggleGuides();
  window.applyCrop = () => void window.studioInstance.applyRectCrop();
});

// ES module export (window compat already set up above)
export { PhotoStudio };
export default PhotoStudio;

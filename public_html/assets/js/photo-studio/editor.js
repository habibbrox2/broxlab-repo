/**
 * Brox Studio - Photo Editor
 * Mobile-first editor with quick tools, perspective crop, background cutout, and print export.
 */

/* exported setTool, rotateImage, flipImage, applyFilter, applyAllFilters, resetFilters, undo, redo, zoomIn, zoomOut, resetZoom, setTint, openFiltersPanel, bgRemove, downloadImage, openPrintSheetModal, closePrintSheetModal, generatePrintSheet, deleteCurrentImage, toggleToolsPanel, applyPerspectiveCrop, resetPerspectiveCrop, clearBackgroundLayer, setBackgroundColor, setBackgroundPreset, setCropPreset, updateCustomCropSize, placeCustomCrop, toggleCropAspectLock */

function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function parseJsonResponse(response) {
  const text = await response.text();
  try {
    return JSON.parse(text);
  } catch {
    return { success: false, error: 'Invalid server response', };
  }
}

function createBlobFromCanvas(canvas, type = 'image/png') {
  return new Promise((resolve, reject) => {
    if (!canvas.toBlob) {
      try {
        const dataUrl = canvas.toDataURL(type);
        const byteString = atob(dataUrl.split(',')[1]);
        const arrayBuffer = new ArrayBuffer(byteString.length);
        const intArray = new Uint8Array(arrayBuffer);
        for (let i = 0; i < byteString.length; i += 1) {
          intArray[i] = byteString.charCodeAt(i);
        }
        resolve(new Blob([arrayBuffer,], { type, }));
      } catch (error) {
        reject(new Error('Failed to create image blob'));
      }
      return;
    }

    canvas.toBlob((blob) => {
      if (!blob) {
        reject(new Error('Failed to create image blob'));
        return;
      }
      resolve(blob);
    }, type);
  });
}

class PhotoStudio {
  constructor() {
    this.canvas = document.getElementById('photoCanvas');
    this.ctx = this.canvas.getContext('2d', { willReadFrequently: true, });
    this.container = document.getElementById('canvasContainer');
    this.placeholder = document.getElementById('uploadPlaceholder');
    this.app = document.getElementById('studioApp');
    this.modeBadge = document.getElementById('canvasModeBadge');
    this.toolHint = document.getElementById('toolHint');

    this.currentImage = null;
    this.cutoutImage = null;
    this.history = [];
    this.historyIndex = -1;
    this.maxHistory = 60;

    this.state = this.getDefaultState();
    this.currentTool = 'select';
    this.isPointerDown = false;
    this.lastPos = null;
    this.dragHandleIndex = -1;
    this.cropStart = null;
    this.cropRect = null;
    this.shapeStart = null;
    this.shapeRect = null;
    this.textSettings = {
      color: '#ffffff',
      size: 42,
    };
    this.brushSize = 20;

    this.trayImages = [];
    this.activeImageIndex = -1;

    this.initializeEventListeners();
    this.loadSavedState();
    this.updateUndoRedoButtons();
    this.updateStatus();
    this.updateToolUi();
  }

  getDefaultState() {
    return {
      brightness: 0,
      contrast: 0,
      saturation: 0,
      tint: null,
      zoom: 1,
      cropMode: 'rect',
      cropPreset: 'free',
      cropAspectRatio: null,
      cropAspectLocked: false,
      customCropWidth: 400,
      customCropHeight: 400,
      perspectivePoints: null,
      background: {
        mode: 'transparent',
        color: '#ffffff',
        preset: null,
      },
    };
  }

  initializeEventListeners() {
    const fileInput = document.getElementById('imageUploadInput');
    fileInput.addEventListener('change', (e) => this.handleFileSelect(e));

    const canvasArea = document.getElementById('canvasArea');
    canvasArea.addEventListener('dragover', (e) => {
      e.preventDefault();
      canvasArea.style.borderColor = 'rgba(249, 115, 22, 0.35)';
    });
    canvasArea.addEventListener('dragleave', (e) => {
      e.preventDefault();
      canvasArea.style.borderColor = '';
    });
    canvasArea.addEventListener('drop', (e) => {
      e.preventDefault();
      canvasArea.style.borderColor = '';
      this.handleFiles(e.dataTransfer.files);
    });

    this.canvas.addEventListener('mousedown', (e) => this.handlePointerDown(e));
    this.canvas.addEventListener('mousemove', (e) => this.handlePointerMove(e));
    this.canvas.addEventListener('mouseup', () => void this.handlePointerUp());
    this.canvas.addEventListener('mouseleave', () => void this.handlePointerUp());

    this.canvas.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: false, });
    this.canvas.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false, });
    this.canvas.addEventListener('touchend', () => void this.handlePointerUp());

    document.addEventListener('keydown', (e) => this.handleKeyDown(e));

    ['brightness', 'contrast', 'saturation',].forEach((prop) => {
      const slider = document.getElementById(`${prop}Slider`);
      const valueEl = document.getElementById(`${prop}Value`);
      slider.addEventListener('input', () => {
        valueEl.textContent = slider.value;
        this.state[prop] = parseInt(slider.value, 10);
        this.render();
      });
    });
  }

  handleFileSelect(e) {
    this.handleFiles(e.target.files);
    e.target.value = '';
  }

  async handleFiles(files) {
    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp',];

    for (const file of files) {
      if (!validTypes.includes(file.type)) {
        this.showToast(`Invalid file: ${file.name}`, 'error');
        continue;
      }

      if (file.size > 10 * 1024 * 1024) {
        this.showToast(`File too large: ${file.name} (max 10MB)`, 'error');
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
      this.showToast('Uploading...', 'info');
      const response = await fetch('/studio/upload', {
        method: 'POST',
        body: formData,
      });
      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.error || 'Upload failed');
      }

      const imageMeta = {
        filename: data.image.filename,
        url: data.image.url,
        original_name: data.image.original_name || file.name,
      };

      this.trayImages.push(imageMeta);
      this.activeImageIndex = this.trayImages.length - 1;
      await this.loadImage(imageMeta.url);
      this.persistState();
      this.renderTray();
      this.showToast('Image uploaded successfully', 'success');
    } catch (error) {
      console.error('Upload error:', error);
      this.showToast(error.message || 'Failed to upload image', 'error');
    }
  }

  loadImage(src) {
    return new Promise((resolve, reject) => {
      const image = new Image();
      image.crossOrigin = 'anonymous';
      image.onload = () => {
        this.currentImage = image;
        this.cutoutImage = null;
        this.state = this.getDefaultState();
        this.syncControls();
        this.canvas.width = image.naturalWidth || image.width;
        this.canvas.height = image.naturalHeight || image.height;
        this.container.style.display = 'block';
        this.placeholder.style.display = 'none';
        this.history = [];
        this.historyIndex = -1;
        this.render();
        this.saveToHistory();
        this.updateStatus();
        this.updateToolUi();
        resolve(image);
      };
      image.onerror = reject;
      image.src = src;
    });
  }

  render(showOverlays = true) {
    if (!this.currentImage) {
      return;
    }

    const foreground = this.getActiveForeground();
    this.ctx.setTransform(1, 0, 0, 1, 0, 0);
    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

    this.drawBackgroundLayer(this.ctx, this.canvas.width, this.canvas.height);
    this.drawForeground(this.ctx, foreground, this.canvas.width, this.canvas.height);

    if (showOverlays) {
      if (this.currentTool === 'crop' && this.cropRect) {
        this.drawRectCropOverlay();
      }

      if (this.currentTool === 'perspective' && this.state.perspectivePoints) {
        this.drawPerspectiveOverlay();
      }

      if (this.currentTool === 'shape' && this.shapeRect) {
        this.drawShapeOverlay();
      }
    }

    this.updateStatus();
  }

  getActiveForeground() {
    return this.cutoutImage || this.currentImage;
  }

  drawBackgroundLayer(ctx, width, height) {
    const { background, } = this.state;

    if (background.mode === 'color') {
      ctx.fillStyle = background.color;
      ctx.fillRect(0, 0, width, height);
      return;
    }

    if (background.mode === 'preset') {
      const gradient = ctx.createLinearGradient(0, 0, width, height);
      const preset = background.preset || 'mint';
      const map = {
        mint: ['#d1fae5', '#0f766e',],
        sunset: ['#ffedd5', '#fb7185',],
        sky: ['#dbeafe', '#2563eb',],
        slate: ['#e2e8f0', '#334155',],
      };
      const [start, end,] = map[preset] || map.mint;
      gradient.addColorStop(0, start);
      gradient.addColorStop(1, end);
      ctx.fillStyle = gradient;
      ctx.fillRect(0, 0, width, height);
    }
  }

  drawForeground(ctx, image, width, height) {
    ctx.drawImage(image, 0, 0, width, height);
    this.applyCanvasFilters(ctx, width, height);

    if (this.state.tint) {
      ctx.fillStyle = `rgba(${this.state.tint.join(',')}, 0.2)`;
      ctx.fillRect(0, 0, width, height);
    }
  }

  applyCanvasFilters(ctx, width, height) {
    const { brightness, contrast, saturation, } = this.state;
    if (brightness === 0 && contrast === 0 && saturation === 0) {
      return;
    }

    const imageData = ctx.getImageData(0, 0, width, height);
    const { data, } = imageData;

    for (let i = 0; i < data.length; i += 4) {
      let r = data[i];
      let g = data[i + 1];
      let b = data[i + 2];

      if (brightness !== 0) {
        const adjust = brightness * 2.55;
        r += adjust;
        g += adjust;
        b += adjust;
      }

      if (contrast !== 0) {
        const factor = (259 * (contrast + 255)) / (255 * (259 - contrast));
        r = factor * (r - 128) + 128;
        g = factor * (g - 128) + 128;
        b = factor * (b - 128) + 128;
      }

      if (saturation !== 0) {
        const gray = 0.2989 * r + 0.5870 * g + 0.114 * b;
        const satFactor = 1 + saturation / 100;
        r = gray + satFactor * (r - gray);
        g = gray + satFactor * (g - gray);
        b = gray + satFactor * (b - gray);
      }

      data[i] = Math.max(0, Math.min(255, r));
      data[i + 1] = Math.max(0, Math.min(255, g));
      data[i + 2] = Math.max(0, Math.min(255, b));
    }

    ctx.putImageData(imageData, 0, 0);
  }

  drawRectCropOverlay() {
    const { x, y, width, height, } = this.cropRect;
    this.ctx.save();
    this.ctx.fillStyle = 'rgba(2, 6, 23, 0.5)';
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    const snapshot = this.createRenderSnapshot();
    this.ctx.drawImage(snapshot, x, y, width, height, x, y, width, height);
    this.ctx.strokeStyle = '#f97316';
    this.ctx.lineWidth = 2;
    this.ctx.setLineDash([6, 6,]);
    this.ctx.strokeRect(x, y, width, height);
    this.ctx.restore();
  }

  drawPerspectiveOverlay() {
    const points = this.state.perspectivePoints;
    this.ctx.save();
    this.ctx.fillStyle = 'rgba(2, 6, 23, 0.35)';
    this.ctx.beginPath();
    this.ctx.rect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.moveTo(points[0].x, points[0].y);
    points.slice(1).forEach((point) => this.ctx.lineTo(point.x, point.y));
    this.ctx.closePath();
    this.ctx.fill('evenodd');

    this.ctx.strokeStyle = '#f97316';
    this.ctx.lineWidth = 2;
    this.ctx.setLineDash([8, 6,]);
    this.ctx.beginPath();
    this.ctx.moveTo(points[0].x, points[0].y);
    points.slice(1).forEach((point) => this.ctx.lineTo(point.x, point.y));
    this.ctx.closePath();
    this.ctx.stroke();
    this.ctx.setLineDash([]);

    points.forEach((point, index) => {
      this.ctx.beginPath();
      this.ctx.fillStyle = '#0f172a';
      this.ctx.arc(point.x, point.y, 10, 0, Math.PI * 2);
      this.ctx.fill();
      this.ctx.strokeStyle = '#f8fafc';
      this.ctx.lineWidth = 2;
      this.ctx.stroke();
      this.ctx.fillStyle = '#f8fafc';
      this.ctx.font = '12px sans-serif';
      this.ctx.fillText(String(index + 1), point.x - 3, point.y + 4);
    });
    this.ctx.restore();
  }

  drawShapeOverlay() {
    const { x, y, width, height, } = this.shapeRect;
    this.ctx.save();
    this.ctx.strokeStyle = '#38bdf8';
    this.ctx.lineWidth = 3;
    this.ctx.setLineDash([8, 5,]);
    this.ctx.strokeRect(x, y, width, height);
    this.ctx.restore();
  }

  handlePointerDown(event) {
    if (!this.currentImage) {
      return;
    }

    const pos = this.getMousePos(event);
    this.isPointerDown = true;

    if (this.currentTool === 'perspective') {
      this.ensurePerspectivePoints();
      this.dragHandleIndex = this.findPerspectiveHandle(pos);
      if (this.dragHandleIndex === -1) {
        this.dragHandleIndex = 0;
      }
      return;
    }

    switch (this.currentTool) {
    case 'crop':
      this.cropStart = pos;
      this.cropRect = { x: pos.x, y: pos.y, width: 0, height: 0, };
      break;
    case 'brush':
    case 'eraser':
      this.lastPos = pos;
      this.drawStroke(pos);
      break;
    case 'shape':
      this.shapeStart = pos;
      this.shapeRect = { x: pos.x, y: pos.y, width: 0, height: 0, };
      break;
    case 'text':
      void this.addTextAtPosition(pos);
      this.isPointerDown = false;
      break;
    default:
      break;
    }
  }

  handlePointerMove(event) {
    if (!this.currentImage || !this.isPointerDown) {
      return;
    }

    const pos = this.getMousePos(event);

    if (this.currentTool === 'perspective' && this.dragHandleIndex >= 0) {
      this.state.perspectivePoints[this.dragHandleIndex] = pos;
      this.render();
      return;
    }

    if (this.currentTool === 'crop' && this.cropStart) {
      this.cropRect = this.buildCropRect(this.cropStart, pos);
      this.render();
      return;
    }

    if (this.currentTool === 'shape' && this.shapeStart) {
      this.shapeRect = {
        x: Math.min(this.shapeStart.x, pos.x),
        y: Math.min(this.shapeStart.y, pos.y),
        width: Math.abs(pos.x - this.shapeStart.x),
        height: Math.abs(pos.y - this.shapeStart.y),
      };
      this.render();
      return;
    }

    if (this.currentTool === 'brush' || this.currentTool === 'eraser') {
      this.drawStroke(pos);
      this.lastPos = pos;
    }
  }

  async handlePointerUp() {
    if (!this.isPointerDown) {
      return;
    }

    if (this.currentTool === 'crop' && this.cropRect && this.cropRect.width > 20 && this.cropRect.height > 20) {
      await this.applyRectCrop();
    } else if (this.currentTool === 'shape' && this.shapeRect && this.shapeRect.width > 10 && this.shapeRect.height > 10) {
      await this.commitShape();
    } else if (this.currentTool === 'brush' || this.currentTool === 'eraser') {
      await this.commitForegroundFromCanvas();
      this.showToast(this.currentTool === 'brush' ? 'Brush applied' : 'Erase applied', 'success');
    }

    this.isPointerDown = false;
    this.lastPos = null;
    this.dragHandleIndex = -1;
    this.cropStart = null;
    this.shapeStart = null;
  }

  handleTouchStart(event) {
    event.preventDefault();
    if (event.touches.length !== 1) {
      return;
    }

    const touch = event.touches[0];
    this.handlePointerDown({
      clientX: touch.clientX,
      clientY: touch.clientY,
    });
  }

  handleTouchMove(event) {
    event.preventDefault();
    if (event.touches.length !== 1) {
      return;
    }

    const touch = event.touches[0];
    this.handlePointerMove({
      clientX: touch.clientX,
      clientY: touch.clientY,
    });
  }

  handleKeyDown(event) {
    if (['INPUT', 'TEXTAREA', 'SELECT',].includes(event.target.tagName)) {
      return;
    }

    switch (event.key.toLowerCase()) {
    case 'v':
      this.setTool('select');
      break;
    case 'c':
      this.setTool('crop');
      break;
    case 'p':
      this.setTool('perspective');
      break;
    case 'b':
      this.setTool('brush');
      break;
    case 'e':
      this.setTool('eraser');
      break;
    case 'z':
      if (event.ctrlKey || event.metaKey) {
        event.preventDefault();
        if (event.shiftKey) {
          void this.redo();
        } else {
          void this.undo();
        }
      }
      break;
    case 'y':
      if (event.ctrlKey || event.metaKey) {
        event.preventDefault();
        void this.redo();
      }
      break;
    default:
      break;
    }
  }

  getMousePos(event) {
    const rect = this.canvas.getBoundingClientRect();
    const scaleX = this.canvas.width / rect.width;
    const scaleY = this.canvas.height / rect.height;

    return {
      x: Math.max(0, Math.min(this.canvas.width, (event.clientX - rect.left) * scaleX)),
      y: Math.max(0, Math.min(this.canvas.height, (event.clientY - rect.top) * scaleY)),
    };
  }

  drawStroke(pos) {
    this.ctx.lineWidth = this.brushSize;
    this.ctx.lineCap = 'round';
    this.ctx.lineJoin = 'round';

    if (this.currentTool === 'eraser') {
      this.ctx.globalCompositeOperation = 'destination-out';
      this.ctx.strokeStyle = 'rgba(0, 0, 0, 1)';
    } else {
      this.ctx.globalCompositeOperation = 'source-over';
      this.ctx.strokeStyle = '#f97316';
    }

    if (this.lastPos) {
      this.ctx.beginPath();
      this.ctx.moveTo(this.lastPos.x, this.lastPos.y);
      this.ctx.lineTo(pos.x, pos.y);
      this.ctx.stroke();
    }

    this.ctx.globalCompositeOperation = 'source-over';
  }

  async addTextAtPosition(pos) {
    if (!this.currentImage) {
      return;
    }

    const text = window.prompt('Enter text for the canvas');
    if (!text) {
      return;
    }

    this.render(false);
    this.ctx.save();
    this.ctx.fillStyle = this.textSettings.color;
    this.ctx.font = `700 ${this.textSettings.size}px "Plus Jakarta Sans", sans-serif`;
    this.ctx.textBaseline = 'top';
    this.ctx.strokeStyle = 'rgba(2, 6, 23, 0.45)';
    this.ctx.lineWidth = 6;
    this.ctx.strokeText(text, pos.x, pos.y);
    this.ctx.fillText(text, pos.x, pos.y);
    this.ctx.restore();

    await this.commitForegroundFromCanvas();
    this.showToast('Text added', 'success');
  }

  async commitShape() {
    this.render(false);
    const { x, y, width, height, } = this.shapeRect;
    this.ctx.save();
    this.ctx.strokeStyle = '#38bdf8';
    this.ctx.lineWidth = 6;
    this.ctx.strokeRect(x, y, width, height);
    this.ctx.fillStyle = 'rgba(56, 189, 248, 0.16)';
    this.ctx.fillRect(x, y, width, height);
    this.ctx.restore();

    this.shapeRect = null;
    await this.commitForegroundFromCanvas();
    this.showToast('Shape added', 'success');
  }

  createRenderSnapshot() {
    const snapshot = document.createElement('canvas');
    snapshot.width = this.canvas.width;
    snapshot.height = this.canvas.height;
    const snapshotCtx = snapshot.getContext('2d');
    this.drawBackgroundLayer(snapshotCtx, snapshot.width, snapshot.height);
    this.drawForeground(snapshotCtx, this.getActiveForeground(), snapshot.width, snapshot.height);
    return snapshot;
  }

  createForegroundSnapshot() {
    const snapshot = document.createElement('canvas');
    snapshot.width = this.canvas.width;
    snapshot.height = this.canvas.height;
    const snapshotCtx = snapshot.getContext('2d');
    snapshotCtx.drawImage(this.getActiveForeground(), 0, 0, snapshot.width, snapshot.height);
    this.applyCanvasFilters(snapshotCtx, snapshot.width, snapshot.height);

    if (this.state.tint) {
      snapshotCtx.fillStyle = `rgba(${this.state.tint.join(',')}, 0.2)`;
      snapshotCtx.fillRect(0, 0, snapshot.width, snapshot.height);
    }

    return snapshot;
  }

  buildCropRect(start, end) {
    let width = Math.abs(end.x - start.x);
    let height = Math.abs(end.y - start.y);
    const directionX = end.x >= start.x ? 1 : -1;
    const directionY = end.y >= start.y ? 1 : -1;
    const ratio = this.getActiveCropRatio();

    if (ratio) {
      if (width === 0 && height === 0) {
        width = this.state.customCropWidth;
        height = this.state.customCropHeight;
      } else if (width / Math.max(height, 1) > ratio) {
        height = width / ratio;
      } else {
        width = height * ratio;
      }
    }

    width = Math.min(width, this.canvas.width);
    height = Math.min(height, this.canvas.height);

    const rect = {
      x: directionX === 1 ? start.x : start.x - width,
      y: directionY === 1 ? start.y : start.y - height,
      width,
      height,
    };

    return this.clampCropRect(rect);
  }

  clampCropRect(rect) {
    const width = Math.max(20, Math.min(rect.width, this.canvas.width));
    const height = Math.max(20, Math.min(rect.height, this.canvas.height));
    const x = Math.max(0, Math.min(rect.x, this.canvas.width - width));
    const y = Math.max(0, Math.min(rect.y, this.canvas.height - height));

    return { x, y, width, height, };
  }

  getActiveCropRatio() {
    if (this.currentTool !== 'crop' || !this.state.cropAspectLocked) {
      return null;
    }

    if (this.state.cropAspectRatio) {
      return this.state.cropAspectRatio;
    }

    const width = Math.max(1, this.state.customCropWidth);
    const height = Math.max(1, this.state.customCropHeight);
    return width / height;
  }

  async commitForegroundFromCanvas() {
    const source = this.currentTool === 'eraser' || this.currentTool === 'brush' || this.currentTool === 'shape' || this.currentTool === 'text'
      ? this.canvas
      : this.createForegroundSnapshot();

    const dataUrl = source.toDataURL('image/png');
    const image = await this.loadImageFromUrl(dataUrl);
    this.currentImage = image;
    this.cutoutImage = null;
    this.state.brightness = 0;
    this.state.contrast = 0;
    this.state.saturation = 0;
    this.state.tint = null;
    this.syncControls();
    this.render();
    this.saveToHistory();
    this.persistState();
  }

  loadImageFromUrl(dataUrl) {
    return new Promise((resolve, reject) => {
      const image = new Image();
      image.onload = () => resolve(image);
      image.onerror = reject;
      image.src = dataUrl;
    });
  }

  setTool(tool) {
    if (!this.currentImage && tool !== 'select') {
      this.showToast('Upload an image first', 'info');
      return;
    }

    this.currentTool = tool;
    this.state.cropMode = tool === 'perspective' ? 'perspective' : tool === 'crop' ? 'rect' : this.state.cropMode;

    if (tool === 'perspective') {
      this.ensurePerspectivePoints();
    }

    if (tool !== 'crop') {
      this.cropRect = null;
    }

    if (tool !== 'shape') {
      this.shapeRect = null;
    }

    this.updateToolUi();
    this.render();
  }

  updateToolUi() {
    document.querySelectorAll('[data-tool-button]').forEach((button) => {
      button.classList.toggle('active', button.dataset.toolButton === this.currentTool);
    });

    const messages = {
      select: ['Select tool', 'No special overlay. Use quick actions on the right panel.',],
      crop: ['Rectangle crop', 'Drag on the canvas to crop a clean rectangle.',],
      perspective: ['Perspective crop', 'Drag the four handles, then press Apply in the Quick Tools section.',],
      brush: ['Brush mode', 'Drag on the image to draw highlight strokes.',],
      eraser: ['Eraser mode', 'Drag on the image to erase pixels and keep transparency.',],
      text: ['Text tool', 'Tap once on the canvas to place text.',],
      shape: ['Shape tool', 'Drag on the canvas to draw a rectangle overlay.',],
    };

    const [badge, hint,] = messages[this.currentTool] || messages.select;
    this.modeBadge.textContent = badge;
    this.toolHint.textContent = hint;
    document.getElementById('perspectiveActions').classList.toggle('active', this.currentTool === 'perspective');
    document.getElementById('cropActions').classList.toggle('active', this.currentTool === 'crop');
    this.canvas.style.cursor = ['crop', 'perspective', 'brush', 'eraser', 'shape',].includes(this.currentTool) ? 'crosshair' : 'default';
  }

  setCropPreset(preset) {
    this.state.cropPreset = preset;

    const presetMap = {
      '1:1': [1, 1,],
      '4:5': [4, 5,],
      '16:9': [16, 9,],
      '40x50': [40, 50,],
      '50x40': [50, 40,],
    };

    if (preset === 'free') {
      this.state.cropAspectLocked = false;
      this.state.cropAspectRatio = null;
    } else if (preset === 'custom') {
      this.state.cropAspectLocked = true;
      this.state.cropAspectRatio = Math.max(1, this.state.customCropWidth) / Math.max(1, this.state.customCropHeight);
    } else if (presetMap[preset]) {
      const [width, height,] = presetMap[preset];
      this.state.cropAspectLocked = true;
      this.state.cropAspectRatio = width / height;
      this.state.customCropWidth = width * 100;
      this.state.customCropHeight = height * 100;
    }

    this.syncCropControls();
  }

  updateCustomCropSize() {
    const widthInput = document.getElementById('customCropWidth');
    const heightInput = document.getElementById('customCropHeight');
    this.state.customCropWidth = Math.max(20, parseInt(widthInput.value, 10) || 400);
    this.state.customCropHeight = Math.max(20, parseInt(heightInput.value, 10) || 400);

    widthInput.value = String(this.state.customCropWidth);
    heightInput.value = String(this.state.customCropHeight);

    if (this.state.cropPreset === 'custom' || this.state.cropAspectLocked) {
      this.state.cropAspectRatio = this.state.customCropWidth / this.state.customCropHeight;
    }
  }

  toggleCropAspectLock() {
    this.state.cropAspectLocked = !this.state.cropAspectLocked;
    if (this.state.cropAspectLocked) {
      this.updateCustomCropSize();
      this.state.cropAspectRatio = this.state.customCropWidth / this.state.customCropHeight;
    } else if (this.state.cropPreset === 'free') {
      this.state.cropAspectRatio = null;
    }
    this.syncCropControls();
  }

  placeCustomCrop() {
    if (!this.currentImage) {
      this.showToast('Upload an image first', 'info');
      return;
    }

    this.setTool('crop');
    this.updateCustomCropSize();

    const ratio = this.getActiveCropRatio() || (this.state.customCropWidth / this.state.customCropHeight);
    let width = Math.min(this.state.customCropWidth, this.canvas.width);
    let height = Math.min(this.state.customCropHeight, this.canvas.height);

    if (ratio) {
      if (width / height > ratio) {
        width = height * ratio;
      } else {
        height = width / ratio;
      }
    }

    if (width > this.canvas.width) {
      width = this.canvas.width;
      height = width / Math.max(ratio, 1);
    }

    if (height > this.canvas.height) {
      height = this.canvas.height;
      width = height * Math.max(ratio, 1);
    }

    this.cropRect = this.clampCropRect({
      x: (this.canvas.width - width) / 2,
      y: (this.canvas.height - height) / 2,
      width,
      height,
    });
    this.render();
  }

  syncCropControls() {
    const preset = document.getElementById('cropPreset');
    const widthInput = document.getElementById('customCropWidth');
    const heightInput = document.getElementById('customCropHeight');
    const toggle = document.getElementById('cropRatioToggle');

    if (preset) {
      preset.value = this.state.cropPreset;
    }
    if (widthInput) {
      widthInput.value = String(this.state.customCropWidth);
    }
    if (heightInput) {
      heightInput.value = String(this.state.customCropHeight);
    }
    if (toggle) {
      toggle.innerHTML = this.state.cropAspectLocked
        ? '<i class="bi bi-link-45deg"></i><span>Ratio Locked</span>'
        : '<i class="bi bi-unlink"></i><span>Free Ratio</span>';
    }
  }

  ensurePerspectivePoints() {
    if (this.state.perspectivePoints) {
      return;
    }

    const insetX = this.canvas.width * 0.12;
    const insetY = this.canvas.height * 0.12;
    this.state.perspectivePoints = [
      { x: insetX, y: insetY, },
      { x: this.canvas.width - insetX, y: insetY, },
      { x: this.canvas.width - insetX, y: this.canvas.height - insetY, },
      { x: insetX, y: this.canvas.height - insetY, },
    ];
  }

  findPerspectiveHandle(pos) {
    const points = this.state.perspectivePoints || [];
    return points.findIndex((point) => Math.hypot(point.x - pos.x, point.y - pos.y) <= 18);
  }

  resetPerspectiveCrop() {
    this.state.perspectivePoints = null;
    if (this.currentTool === 'perspective') {
      this.ensurePerspectivePoints();
      this.render();
    }
  }

  async applyPerspectiveCrop() {
    if (!this.currentImage) {
      return;
    }

    this.ensurePerspectivePoints();
    const quad = this.state.perspectivePoints;
    const destinationWidth = Math.max(60, Math.round((this.distance(quad[0], quad[1]) + this.distance(quad[3], quad[2])) / 2));
    const destinationHeight = Math.max(60, Math.round((this.distance(quad[0], quad[3]) + this.distance(quad[1], quad[2])) / 2));

    const source = this.createForegroundSnapshot();
    const output = document.createElement('canvas');
    output.width = destinationWidth;
    output.height = destinationHeight;
    const outputCtx = output.getContext('2d');

    const steps = Math.max(20, Math.round(destinationWidth / 18));
    for (let i = 0; i < steps; i += 1) {
      const t0 = i / steps;
      const t1 = (i + 1) / steps;
      const sourceTopLeft = this.interpolatePoint(quad[0], quad[1], t0);
      const sourceTopRight = this.interpolatePoint(quad[0], quad[1], t1);
      const sourceBottomLeft = this.interpolatePoint(quad[3], quad[2], t0);
      const sourceBottomRight = this.interpolatePoint(quad[3], quad[2], t1);

      const destX = t0 * destinationWidth;
      const nextDestX = t1 * destinationWidth;
      const stripWidth = Math.max(1, nextDestX - destX);

      const patch = this.extractPerspectiveStrip(
        source,
        sourceTopLeft,
        sourceTopRight,
        sourceBottomLeft,
        sourceBottomRight,
        stripWidth,
        destinationHeight
      );

      outputCtx.drawImage(patch, destX, 0, stripWidth, destinationHeight);
    }

    this.canvas.width = output.width;
    this.canvas.height = output.height;
    this.currentImage = await this.loadImageFromUrl(output.toDataURL('image/png'));
    this.cutoutImage = null;
    this.state.perspectivePoints = null;
    this.state.brightness = 0;
    this.state.contrast = 0;
    this.state.saturation = 0;
    this.state.tint = null;
    this.syncControls();
    this.render();
    this.saveToHistory();
    this.showToast('Perspective crop applied', 'success');
  }

  extractPerspectiveStrip(sourceCanvas, topLeft, topRight, bottomLeft, bottomRight, width, height) {
    const strip = document.createElement('canvas');
    strip.width = Math.max(1, Math.round(width));
    strip.height = Math.max(1, Math.round(height));
    const stripCtx = strip.getContext('2d');

    for (let y = 0; y < strip.height; y += 1) {
      const ty = strip.height <= 1 ? 0 : y / (strip.height - 1);
      const start = this.interpolatePoint(topLeft, bottomLeft, ty);
      const end = this.interpolatePoint(topRight, bottomRight, ty);
      const sourceX = Math.min(start.x, end.x);
      const sourceY = Math.min(start.y, end.y);
      const sampleWidth = Math.max(1, Math.abs(end.x - start.x));
      const sampleHeight = Math.max(1, Math.abs(end.y - start.y));

      stripCtx.drawImage(
        sourceCanvas,
        sourceX,
        sourceY,
        sampleWidth,
        sampleHeight,
        0,
        y,
        strip.width,
        1
      );
    }

    return strip;
  }

  interpolatePoint(a, b, t) {
    return {
      x: a.x + (b.x - a.x) * t,
      y: a.y + (b.y - a.y) * t,
    };
  }

  distance(a, b) {
    return Math.hypot(b.x - a.x, b.y - a.y);
  }

  async applyRectCrop() {
    this.render(false);
    const { x, y, width, height, } = this.cropRect;
    const source = this.createForegroundSnapshot();
    const output = document.createElement('canvas');
    output.width = width;
    output.height = height;
    output.getContext('2d').drawImage(source, x, y, width, height, 0, 0, width, height);

    this.canvas.width = output.width;
    this.canvas.height = output.height;
    this.currentImage = await this.loadImageFromUrl(output.toDataURL('image/png'));
    this.cutoutImage = null;
    this.cropRect = null;
    this.syncControls();
    this.render();
    this.saveToHistory();
    this.showToast('Image cropped', 'success');
  }

  applyFilter() {
    this.render();
  }

  async applyAllFilters() {
    if (!this.currentImage) {
      this.showToast('No image to edit', 'error');
      return;
    }

    const snapshot = this.createForegroundSnapshot();
    this.currentImage = await this.loadImageFromUrl(snapshot.toDataURL('image/png'));
    this.cutoutImage = null;
    this.state.brightness = 0;
    this.state.contrast = 0;
    this.state.saturation = 0;
    this.state.tint = null;
    this.syncControls();
    this.render();
    this.saveToHistory();
    this.showToast('Filters applied', 'success');
  }

  resetFilters() {
    if (!this.currentImage) {
      return;
    }

    this.state.brightness = 0;
    this.state.contrast = 0;
    this.state.saturation = 0;
    this.state.tint = null;
    this.syncControls();
    this.render();
    this.showToast('Adjustment preview reset', 'info');
  }

  async rotateImage(angle = 90) {
    if (!this.currentImage) {
      this.showToast('No image to rotate', 'error');
      return;
    }

    const source = this.createForegroundSnapshot();
    const radians = (angle * Math.PI) / 180;
    const swap = Math.abs(angle) % 180 === 90;
    const output = document.createElement('canvas');
    output.width = swap ? source.height : source.width;
    output.height = swap ? source.width : source.height;
    const outputCtx = output.getContext('2d');

    outputCtx.translate(output.width / 2, output.height / 2);
    outputCtx.rotate(radians);
    outputCtx.drawImage(source, -source.width / 2, -source.height / 2);

    this.currentImage = await this.loadImageFromUrl(output.toDataURL('image/png'));
    this.cutoutImage = null;
    this.canvas.width = output.width;
    this.canvas.height = output.height;
    this.render();
    this.saveToHistory();
    this.showToast(`Rotated ${angle}\u00B0`, 'success');
  }

  async flipImage(direction) {
    if (!this.currentImage) {
      this.showToast('No image to flip', 'error');
      return;
    }

    const source = this.createForegroundSnapshot();
    const output = document.createElement('canvas');
    output.width = source.width;
    output.height = source.height;
    const outputCtx = output.getContext('2d');

    outputCtx.save();
    if (direction === 'horizontal') {
      outputCtx.translate(output.width, 0);
      outputCtx.scale(-1, 1);
    } else {
      outputCtx.translate(0, output.height);
      outputCtx.scale(1, -1);
    }
    outputCtx.drawImage(source, 0, 0);
    outputCtx.restore();

    this.currentImage = await this.loadImageFromUrl(output.toDataURL('image/png'));
    this.cutoutImage = null;
    this.render();
    this.saveToHistory();
    this.showToast(direction === 'horizontal' ? 'Flipped horizontally' : 'Flipped vertically', 'success');
  }

  async bgRemove() {
    if (!this.currentImage) {
      this.showToast('No image to edit', 'error');
      return;
    }

    try {
      this.showToast('Removing background...', 'info');
      const blob = await this.getForegroundBlob();
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

      this.cutoutImage = await this.loadImageFromUrl(result.cutout_url);
      this.state.background.mode = this.state.background.mode === 'transparent' ? 'color' : this.state.background.mode;
      if (this.state.background.mode === 'color' && !this.state.background.color) {
        this.state.background.color = '#ffffff';
      }
      this.render();
      this.saveToHistory();
      this.showToast('Subject isolated with transparent background', 'success');
    } catch (error) {
      console.error('Background remove error:', error);
      this.showToast(error.message || 'Background removal failed', 'error');
    }
  }

  getForegroundBlob() {
    const snapshot = this.createForegroundSnapshot();
    return createBlobFromCanvas(snapshot, 'image/png');
  }

  setTint(color) {
    this.state.tint = Array.isArray(color) ? color : null;
    document.querySelectorAll('.studio-swatch[data-color]').forEach((swatch) => {
      swatch.classList.toggle('active', swatch.dataset.color === (color ? color.join(',') : 'none'));
    });
    this.render();
  }

  setBackgroundColor(color) {
    this.state.background.mode = 'color';
    this.state.background.color = color;
    this.state.background.preset = null;
    document.querySelectorAll('[data-background-preset]').forEach((button) => button.classList.remove('active'));
    this.render();
    this.persistState();
  }

  setBackgroundPreset(preset) {
    this.state.background.mode = 'preset';
    this.state.background.preset = preset;
    document.querySelectorAll('[data-background-preset]').forEach((button) => {
      button.classList.toggle('active', button.dataset.backgroundPreset === preset);
    });
    this.render();
    this.persistState();
  }

  clearBackgroundLayer() {
    this.state.background.mode = 'transparent';
    this.state.background.preset = null;
    this.render();
    document.querySelectorAll('[data-background-preset]').forEach((button) => button.classList.remove('active'));
  }

  saveToHistory() {
    if (!this.currentImage) {
      return;
    }

    const snapshot = {
      foreground: this.currentImage.src,
      cutout: this.cutoutImage?.src || null,
      state: JSON.parse(JSON.stringify(this.state)),
    };

    this.history = this.history.slice(0, this.historyIndex + 1);
    this.history.push(snapshot);
    if (this.history.length > this.maxHistory) {
      this.history.shift();
    } else {
      this.historyIndex += 1;
    }
    this.updateUndoRedoButtons();
  }

  async undo() {
    if (this.historyIndex <= 0) {
      return;
    }

    this.historyIndex -= 1;
    await this.restoreSnapshot(this.history[this.historyIndex]);
  }

  async redo() {
    if (this.historyIndex >= this.history.length - 1) {
      return;
    }

    this.historyIndex += 1;
    await this.restoreSnapshot(this.history[this.historyIndex]);
  }

  async restoreSnapshot(snapshot) {
    this.currentImage = await this.loadImageFromUrl(snapshot.foreground);
    this.cutoutImage = snapshot.cutout ? await this.loadImageFromUrl(snapshot.cutout) : null;
    this.state = snapshot.state;
    this.canvas.width = this.currentImage.naturalWidth || this.currentImage.width;
    this.canvas.height = this.currentImage.naturalHeight || this.currentImage.height;
    this.syncControls();
    this.render();
    this.updateUndoRedoButtons();
  }

  updateUndoRedoButtons() {
    const undoDisabled = this.historyIndex <= 0;
    const redoDisabled = this.historyIndex >= this.history.length - 1;
    document.getElementById('undoBtn').disabled = undoDisabled;
    document.getElementById('redoBtn').disabled = redoDisabled;
  }

  zoomIn() {
    this.state.zoom = Math.min(this.state.zoom * 1.2, 5);
    this.applyZoom();
  }

  zoomOut() {
    this.state.zoom = Math.max(this.state.zoom / 1.2, 0.15);
    this.applyZoom();
  }

  resetZoom() {
    this.state.zoom = 1;
    this.applyZoom();
  }

  applyZoom() {
    this.container.style.transform = `scale(${this.state.zoom})`;
    document.getElementById('zoomLevel').textContent = `${Math.round(this.state.zoom * 100)}%`;
  }

  toggleToolsPanel(forceOpen) {
    const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !this.app.classList.contains('sidebar-open');
    this.app.classList.toggle('sidebar-open', shouldOpen);
  }

  openFiltersPanel() {
    document.getElementById('adjustmentsPanel')?.scrollIntoView({ behavior: 'smooth', block: 'start', });
    this.toggleToolsPanel(true);
  }

  async persistActiveImageToTray() {
    if (this.activeImageIndex < 0 || !this.currentImage) {
      return null;
    }

    const blob = await this.getCompositeBlob();
    const formData = new FormData();
    formData.append('image', blob, 'studio-composition.png');
    formData.append('csrf_token', getCsrfToken());

    const response = await fetch('/studio/save', {
      method: 'POST',
      body: formData,
    });
    const result = await parseJsonResponse(response);

    if (!response.ok || !result.success) {
      throw new Error(result.error || 'Failed to persist current composition');
    }

    const active = this.trayImages[this.activeImageIndex];
    this.trayImages[this.activeImageIndex] = {
      ...active,
      filename: result.image.filename,
      url: result.image.url,
    };
    this.persistState();
    this.renderTray();
    return result.image.url;
  }

  getCompositeBlob() {
    const snapshot = this.createRenderSnapshot();
    return createBlobFromCanvas(snapshot, 'image/png');
  }

  async saveImage(format = 'png') {
    if (!this.currentImage) {
      this.showToast('No image to download', 'error');
      return null;
    }

    const mime = format === 'jpg' ? 'image/jpeg' : 'image/png';
    const snapshot = this.createRenderSnapshot();
    const dataUrl = snapshot.toDataURL(mime, 0.92);
    const blob = await (await fetch(dataUrl)).blob();

    try {
      const formData = new FormData();
      formData.append('image', blob, `studio-export.${format}`);
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
      link.download = `brox-studio-${Date.now()}.${format}`;
      link.click();
      this.showToast('Image downloaded', 'success');
      return result.image.url;
    } catch (error) {
      console.error('Save error:', error);
      const link = document.createElement('a');
      link.href = dataUrl;
      link.download = `brox-studio-${Date.now()}.${format}`;
      link.click();
      this.showToast('Image downloaded locally', 'info');
      return dataUrl;
    }
  }

  async loadFromTray(index) {
    if (index < 0 || index >= this.trayImages.length) {
      return;
    }

    this.activeImageIndex = index;
    const imageMeta = this.trayImages[index];
    try {
      await this.loadImage(imageMeta.url);
      this.persistState();
      this.renderTray();
    } catch (error) {
      console.error('Tray load error:', error);
      this.showToast('Saved tray image could not be loaded', 'error');
    }
  }

  renderTray() {
    const tray = document.getElementById('imageTray');
    tray.innerHTML = '';

    if (this.trayImages.length === 0) {
      tray.innerHTML = '<p class="studio-helper-text">No uploaded images yet.</p>';
      return;
    }

    this.trayImages.forEach((image, index) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = `tray-item ${index === this.activeImageIndex ? 'active' : ''}`;
      item.onclick = () => void this.loadFromTray(index);
      item.innerHTML = `<img src="${image.url}" alt="${image.original_name || `Image ${index + 1}`}">`;

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'tray-item-remove';
      removeBtn.innerHTML = '<i class="bi bi-x"></i>';
      removeBtn.onclick = (event) => {
        event.stopPropagation();
        void this.deleteTrayImage(index);
      };

      item.appendChild(removeBtn);
      tray.appendChild(item);
    });
  }

  async deleteTrayImage(index) {
    const imageMeta = this.trayImages[index];
    if (!imageMeta || !window.confirm('Delete this image?')) {
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
    } catch (error) {
      console.warn('Delete endpoint warning:', error);
    }

    this.trayImages.splice(index, 1);
    if (this.trayImages.length === 0) {
      this.activeImageIndex = -1;
      this.currentImage = null;
      this.cutoutImage = null;
      this.container.style.display = 'none';
      this.placeholder.style.display = 'flex';
      this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
      this.history = [];
      this.historyIndex = -1;
      this.updateUndoRedoButtons();
      this.updateStatus();
    } else {
      this.activeImageIndex = Math.min(index, this.trayImages.length - 1);
      await this.loadFromTray(this.activeImageIndex);
    }

    this.persistState();
    this.renderTray();
    this.showToast('Image deleted', 'success');
  }

  openPrintSheetModal() {
    if (this.trayImages.length === 0) {
      this.showToast('Add images before generating a print sheet', 'info');
      return;
    }

    const modal = document.getElementById('printSheetModal');
    const list = document.getElementById('printSheetImageList');
    list.innerHTML = '';

    this.trayImages.forEach((image, index) => {
      const item = document.createElement('button');
      item.type = 'button';
      const isSelected = index === this.activeImageIndex;
      item.className = `tray-item${isSelected ? ' active selected' : ''}`;
      item.dataset.printImage = 'true';
      item.dataset.url = image.url;
      item.innerHTML = `<img src="${image.url}" alt="${image.original_name || `Image ${index + 1}`}">`;
      item.onclick = () => {
        item.classList.toggle('selected');
      };
      list.appendChild(item);
    });

    modal.style.display = 'flex';
  }

  closePrintSheetModal() {
    document.getElementById('printSheetModal').style.display = 'none';
  }

  async generatePrintSheet() {
    try {
      if (this.activeImageIndex >= 0 && this.currentImage) {
        await this.persistActiveImageToTray();
      }

      const selectedImages = Array.from(document.querySelectorAll('[data-print-image].selected'))
        .map((item) => item.dataset.url)
        .filter(Boolean);

      if (selectedImages.length === 0) {
        this.showToast('Select at least one image for printing', 'error');
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
          size: document.getElementById('printSize').value,
          csrf_token: getCsrfToken(),
        }),
      });
      const result = await parseJsonResponse(response);

      if (!response.ok || !result.success) {
        throw new Error(result.error || 'Print generation failed');
      }

      window.open(result.download_url, '_blank', 'noopener');
      this.closePrintSheetModal();
      this.showToast('Print sheet generated', 'success');
    } catch (error) {
      console.error('Print sheet error:', error);
      this.showToast(error.message || 'Print sheet generation failed', 'error');
    }
  }

  updateStatus() {
    const text = this.currentImage
      ? `${this.canvas.width} x ${this.canvas.height} px`
      : 'No image loaded';
    document.getElementById('imageInfo').textContent = text;
  }

  syncControls() {
    document.getElementById('brightnessSlider').value = this.state.brightness;
    document.getElementById('brightnessValue').textContent = String(this.state.brightness);
    document.getElementById('contrastSlider').value = this.state.contrast;
    document.getElementById('contrastValue').textContent = String(this.state.contrast);
    document.getElementById('saturationSlider').value = this.state.saturation;
    document.getElementById('saturationValue').textContent = String(this.state.saturation);
    document.getElementById('backgroundColorPicker').value = this.state.background.color || '#ffffff';
    this.setTint(this.state.tint);
    this.syncCropControls();
    this.applyZoom();
  }

  persistState() {
    const payload = {
      version: 2,
      trayImages: this.trayImages,
      activeImageIndex: this.activeImageIndex,
      background: this.state.background,
      lastTool: this.currentTool,
    };
    window.localStorage.setItem('broxstudio_state', JSON.stringify(payload));
  }

  loadSavedState() {
    const saved = window.localStorage.getItem('broxstudio_state');
    if (!saved) {
      this.renderTray();
      return;
    }

    try {
      const payload = JSON.parse(saved);
      if (Array.isArray(payload)) {
        this.trayImages = payload.filter((item) => item?.url && item?.filename);
      } else {
        this.trayImages = Array.isArray(payload.trayImages) ? payload.trayImages.filter((item) => item?.url && item?.filename) : [];
      }

      if (this.trayImages.length > 0) {
        this.activeImageIndex = Math.max(0, Math.min(payload.activeImageIndex || 0, this.trayImages.length - 1));
        if (payload.background) {
          this.state.background = {
            ...this.state.background,
            ...payload.background,
          };
        }
        this.currentTool = payload.lastTool || 'select';
        void this.loadFromTray(this.activeImageIndex);
      } else {
        this.renderTray();
      }
    } catch (_error) {
      window.localStorage.removeItem('broxstudio_state');
      this.renderTray();
    }
  }

  showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}`;
    container.appendChild(toast);

    window.setTimeout(() => {
      toast.remove();
    }, 3200);
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
  window.setTint = (color) => window.studioInstance.setTint(color);
  window.openFiltersPanel = () => window.studioInstance.openFiltersPanel();
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
  window.applyPerspectiveCrop = () => void window.studioInstance.applyPerspectiveCrop();
  window.resetPerspectiveCrop = () => window.studioInstance.resetPerspectiveCrop();
  window.clearBackgroundLayer = () => window.studioInstance.clearBackgroundLayer();
  window.setBackgroundColor = (color) => window.studioInstance.setBackgroundColor(color);
  window.setBackgroundPreset = (preset) => window.studioInstance.setBackgroundPreset(preset);
  window.setCropPreset = (preset) => window.studioInstance.setCropPreset(preset);
  window.updateCustomCropSize = () => window.studioInstance.updateCustomCropSize();
  window.placeCustomCrop = () => window.studioInstance.placeCustomCrop();
  window.toggleCropAspectLock = () => window.studioInstance.toggleCropAspectLock();
});

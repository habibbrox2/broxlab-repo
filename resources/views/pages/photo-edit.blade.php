@extends('layouts.app')

@section('title')
  AI Photo Edit — এআই ফটো এডিট | {{ $appSettings['site_name'] ?? 'BroxLab' }}
@endsection

@push('styles')
<style>
  .drop-zone {
    border: 2px dashed rgb(var(--border, 209 213 219));
    transition: all 0.2s ease;
  }
  .drop-zone.dragover {
    border-color: rgb(79 70 229);
    background: rgba(79, 70, 229, 0.05);
  }
  .preview-container {
    aspect-ratio: 4/3;
    object-fit: contain;
  }
  .preset-btn {
    transition: all 0.15s ease;
  }
  .preset-btn:hover {
    transform: translateY(-1px);
  }
  .toast-error {
    animation: slideDown 0.3s ease-out;
  }
  @keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .gallery-thumb {
    aspect-ratio: 1;
    object-fit: cover;
    transition: transform 0.2s ease;
  }
  .gallery-thumb:hover {
    transform: scale(1.05);
  }
</style>
@endpush

@section('content')
<div class="max-w-6xl mx-auto px-4 py-10">
  {{-- Header --}}
  <div class="text-center mb-10">
    <div class="flex items-center justify-center gap-3 mb-4">
      <i class="lucide lucide-wand w-8 h-8 text-indigo-600" aria-hidden="true"></i>
      <h1 class="text-3xl font-bold text-[rgb(var(--text))]">AI Photo Edit</h1>
    </div>
    <p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
      আপলোড করুন আপনার ছবি এবং একটি নির্দেশনা লিখুন (যেমন: "remove the background",
      "make it vintage", "enhance the sky")। আমরা AI ব্যবহার করে আপনার ছবিটি এডিট করব।
    </p>
  </div>

  {{-- Inline error toast --}}
  @if(session('error'))
    <div class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/40 toast-error">
      <p class="text-sm text-red-800 dark:text-red-200 flex items-center gap-2">
        <i class="lucide lucide-alert-circle w-4 h-4 flex-shrink-0"></i>
        {{ session('error') }}
      </p>
    </div>
  @endif

  {{-- Not configured callout --}}
  @if(!config('app.ai_enabled', false))
    <div class="mb-6 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40">
      <p class="text-sm text-amber-800 dark:text-amber-200">
        <i class="lucide lucide-alert-triangle w-4 h-4 inline mr-2" aria-hidden="true"></i>
        AI Photo Edit কাজ করতে একটি ওপেনএআই-সাপত্ত্বক সেবা প্রোভাইডার কনফিগার করুন।
        অ্যাডমিন প্যানেলে <a href="/admin/aisystem" class="underline font-medium">AI System → Providers</a> থেকে যোগ করুন।
      </p>
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    {{-- Main editor panel --}}
    <div class="lg:col-span-2">
      <div class="bg-[rgb(var(--surface))] border border-[rgb(var(--border))] rounded-2xl shadow-lg overflow-hidden">
        <div class="p-6">
          {{-- Upload area --}}
          <form id="uploadForm" enctype="multipart/form-data" class="mb-6">
            @csrf
            <label for="imageUpload"
                   class="drop-zone flex flex-col items-center justify-center gap-3 p-8 rounded-xl bg-[rgb(var(--surface-soft))] cursor-pointer hover:bg-[rgb(var(--surface))] transition-all duration-200 border-2 border-dashed">
              <i class="lucide lucide-upload-cloud w-12 h-12 text-indigo-500" aria-hidden="true"></i>
              <div class="text-center">
                <span class="font-semibold text-[rgb(var(--text))]">
                  ছবি আপলোড করতে ক্লিক করুন
                </span>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                  PNG, JPG, অথবা WebP — সর্বোচ্চ 5 MB
                </p>
              </div>
              <input type="file" id="imageUpload" name="image" accept="image/png,image/jpeg,image/webp" class="hidden" />
            </label>
          </form>

          {{-- Preview --}}
          <div id="previewWrapper" class="hidden mb-6">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-medium text-[rgb(var(--text))]">আপনার ছবি</span>
              <button type="button" id="removeImage" class="text-xs text-red-500 hover:text-red-700">
                <i class="lucide lucide-x w-4 h-4 inline mr-1" aria-hidden="true"></i> Remove
              </button>
            </div>
            <img id="preview" class="w-full preview-container rounded-xl border border-[rgb(var(--border))]" alt="Preview" />
          </div>

          {{-- Edit controls --}}
          <form id="editForm">
            @csrf
            <div class="space-y-4">
              {{-- Tool selector --}}
              <div>
                <label class="block text-sm font-medium text-[rgb(var(--text))] mb-2">টুল নির্বাচন করুন</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                  <button type="button" data-tool="edit"
                          class="tool-btn active bg-indigo-100 dark:bg-indigo-900/40 border-2 border-indigo-500 text-indigo-700 dark:text-indigo-300 rounded-xl p-3 flex flex-col items-center gap-2">
                    <i class="lucide lucide-wand w-5 h-5" aria-hidden="true"></i>
                    <span class="text-sm font-medium">AI এডিট (Prompt)</span>
                  </button>
                  <button type="button" data-tool="remove-object"
                          class="tool-btn bg-[rgb(var(--surface-soft))] border-2 border-[rgb(var(--border))] text-[rgb(var(--text))] rounded-xl p-3 flex flex-col items-center gap-2 hover:bg-slate-200 dark:hover:bg-slate-700">
                    <i class="lucide lucide-eraser w-5 h-5" aria-hidden="true"></i>
                    <span class="text-sm font-medium">অবজেক্ট রিমুভ</span>
                  </button>
                  <button type="button" id="removeBgBtn"
                          class="bg-[rgb(var(--surface-soft))] border-2 border-[rgb(var(--border))] text-[rgb(var(--text))] rounded-xl p-3 flex flex-col items-center gap-2 hover:bg-slate-200 dark:hover:bg-slate-700">
                    <i class="lucide lucide-scissors w-5 h-5" aria-hidden="true"></i>
                    <span class="text-sm font-medium">ব্যাকগ্রাউন্ড বাদ দিন</span>
                  </button>
                </div>
              </div>

              {{-- Prompt + presets (shown when tool is 'edit') --}}
              <div id="editToolPanel">
                <div>
                  <label for="prompt" class="block text-sm font-medium text-[rgb(var(--text))] mb-2">
                    কীভাবে এডিট করবেন? (Prompt)
                  </label>
                  <textarea id="prompt" name="prompt" rows="3"
                            placeholder="যেমন: remove the background, make it vintage, enhance the sky and make it sharper, change the background to a beach sunset..."
                            class="w-full px-4 py-3 rounded-xl bg-[rgb(var(--surface-soft))] border border-[rgb(var(--border))] text-[rgb(var(--text))] placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"></textarea>
                </div>

                {{-- Quick preset buttons --}}
                <div class="flex flex-wrap gap-2">
                  <span class="text-xs text-[rgb(var(--muted))] mr-2 self-center">প্রিসেট:</span>
                  <button type="button" data-preset="enhance"
                          class="preset-btn px-3 py-1.5 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-xs font-medium">উন্নত করুন</button>
                  <button type="button" data-preset="vintage"
                          class="preset-btn px-3 py-1.5 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-xs font-medium">ভিন্টেজ স্টাইল</button>
                  <button type="button" data-preset="background"
                          class="preset-btn px-3 py-1.5 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-xs font-medium">বেজ বদলাও</button>
                  <button type="button" data-preset="anime"
                          class="preset-btn px-3 py-1.5 rounded-lg bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300 text-xs font-medium">অ্যানিমে স্টাইল</button>
                </div>
              </div>

              {{-- Object removal panel (shown when tool is 'remove-object') --}}
              <div id="removeObjectPanel" class="hidden">
                <div class="flex items-center gap-3 mb-3">
                  <i class="lucide lucide-eraser w-5 h-5 text-slate-500" aria-hidden="true"></i>
                  <p class="text-sm text-slate-600 dark:text-slate-400">
                    মুড়ে যে অবজেক্টটি রিমুভ করতে চান সেটি আপলোড করুন (বালকালীন আকৃতি। 1=সাদা, 0=লাল)।
                  </p>
                </div>
                <div class="space-y-3">
                  <label for="maskUpload" class="block text-sm font-medium text-[rgb(var(--text))]">Mask আপলোড</label>
                  <label for="maskUpload"
                         class="drop-zone flex items-center justify-center gap-3 p-6 rounded-xl bg-[rgb(var(--surface-soft))] cursor-pointer hover:bg-[rgb(var(--surface))] transition-all duration-200 border-2 border-dashed">
                    <i class="lucide lucide-upload w-8 h-8 text-indigo-500" aria-hidden="true"></i>
                    <span class="font-medium text-[rgb(var(--text))]" id="maskLabel">মাস্ক ছবি আপলোড করুন</span>
                    <input type="file" id="maskUpload" name="mask" accept="image/png,image/jpeg,image/webp" class="hidden" />
                  </label>
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    মাস্কের যেসব অংশ সাদা (255) আছে, সেখান থেকে অবজেক্টটি আপনার মূল ছবিতে রিমুভ হবে।
                  </p>
                </div>
              </div>

              {{-- Mode + Size selector --}}
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label for="mode" class="block text-xs font-medium text-[rgb(var(--muted))] mb-1">মোড</label>
                  <select id="mode" name="mode"
                          class="w-full px-3 py-2 rounded-lg bg-[rgb(var(--surface-soft))] border border-[rgb(var(--border))] text-[rgb(var(--text))] focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="edit">এডিট (Edit uploaded image)</option>
                    <option value="gen">নতুন জেনারেট (Generate from prompt only)</option>
                  </select>
                </div>
                <div>
                  <label for="size" class="block text-xs font-medium text-[rgb(var(--muted))] mb-1">আকার</label>
                  <select id="size" name="size"
                          class="w-full px-3 py-2 rounded-lg bg-[rgb(var(--surface-soft))] border border-[rgb(var(--border))] text-[rgb(var(--text))] focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="1024x1024">1024×1024 (Square)</option>
                    <option value="1792x1024">1792×1024 (Landscape)</option>
                    <option value="1024x1792">1024×1792 (Portrait)</option>
                  </select>
                </div>
                <div class="flex items-end">
                  <button type="submit" id="editBtn"
                          class="w-full py-3 px-6 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:shadow-indigo-500/40">
                    <span id="editBtnText">AI দিয়ে এডিট করুন</span>
                    <i class="lucide lucide-wand w-5 h-5 ml-2 inline" aria-hidden="true"></i>
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Result gallery (right sidebar) --}}
    <div class="lg:col-span-1">
      <div class="bg-[rgb(var(--surface))] border border-[rgb(var(--border))] rounded-2xl shadow-lg p-6 sticky top-6 h-fit">
        <h3 class="font-semibold text-[rgb(var(--text))] mb-4 flex items-center gap-2">
          <i class="lucide lucide-images w-5 h-5" aria-hidden="true"></i>
          ফলাফল গ্যালারি
        </h3>
        <div id="resultGallery" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-2 gap-3">
          <p class="text-xs text-slate-500 dark:text-slate-400 col-span-full">আপনার এডিটেড ছবি এখানে প্র�র্শিত হবে।</p>
        </div>
      </div>
    </div>
  </div>

  {{-- Tips section --}}
  <div class="mt-10 grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-5 rounded-xl bg-[rgb(var(--surface-soft))] border border-[rgb(var(--border))]">
      <h3 class="font-semibold text-[rgb(var(--text))] mb-2 flex items-center gap-2">
        <i class="lucide lucide-lightbulb w-5 h-5 text-amber-500" aria-hidden="true"></i>
        ভালো প্রম্পটের টিপস
      </h3>
      <ul class="text-sm text-slate-600 dark:text-slate-400 space-y-1">
        <li>• নির্দিষ্ট উল্লেখ করুন কী পরিবর্তন করতে চান</li>
        <li>• স্টাইল এবং রঙগুলি উল্লেখ করুন (যেমন: "vintage film look")</li>
        <li>• ব্যাকগ্রাউন্ড পরিবর্তনের জন্য "replace background with..." লিখুন</li>
      </ul>
    </div>
    <div class="p-5 rounded-xl bg-[rgb(var(--surface-soft))] border border-[rgb(var(--border))]">
      <h3 class="font-semibold text-[rgb(var(--text))] mb-2 flex items-center gap-2">
        <i class="lucide lucide-info w-5 h-5 text-indigo-500" aria-hidden="true"></i>
        সম্পর্কে তথ্য
      </h3>
      <p class="text-sm text-slate-600 dark:text-slate-400">
        আপনার ছবি সংরক্ষণ করা হয় না। সর্বোচ্চ 5 MB পর্যন্ত আপলোড করুন।
        এআই সেবা প্রদানকারীর সীমাবদ্ধতা ও মূল্য প্রয়োগ্য হতে পারে।
      </p>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const uploadInput      = document.getElementById('imageUpload');
  const previewWrapper   = document.getElementById('previewWrapper');
  const previewImg       = document.getElementById('preview');
  const editForm         = document.getElementById('editForm');
  const editBtn          = document.getElementById('editBtn');
  const editBtnText      = document.getElementById('editBtnText');
  const removeBtn        = document.getElementById('removeImage');
  const resultGallery    = document.getElementById('resultGallery');
  const maskUpload       = document.getElementById('maskUpload');
  const maskLabel        = document.getElementById('maskLabel');
  const removeBgBtn      = document.getElementById('removeBgBtn');
  const editToolPanel    = document.getElementById('editToolPanel');
  const removeObjectPanel = document.getElementById('removeObjectPanel');

  let uploadedFile          = null;
  let uploadedFileUrl        = null;
  let uploadedMaskFile       = null;
  let activeTool             = 'edit';
  const recentResults = []; // in-memory gallery

  // --- Tool switching ---
  document.querySelectorAll('.tool-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tool-btn').forEach(b => {
        b.classList.remove('active', 'border-indigo-500');
        b.classList.add('border-[rgb(var(--border))]');
        b.classList.remove('bg-indigo-100', 'dark:bg-indigo-900/40', 'text-indigo-700', 'dark:text-indigo-300');
        b.classList.add('bg-[rgb(var(--surface-soft))]', 'text-[rgb(var(--text))]');
      });
      btn.classList.add('active', 'border-indigo-500');
      btn.classList.remove('bg-[rgb(var(--surface-soft))]', 'text-[rgb(var(--text))]');
      btn.classList.add('bg-indigo-100', 'dark:bg-indigo-900/40', 'text-indigo-700', 'dark:text-indigo-300');
      activeTool = btn.dataset.tool;
      editToolPanel.classList.toggle('hidden', activeTool !== 'edit');
      removeObjectPanel.classList.toggle('hidden', activeTool !== 'remove-object');
    });
  });

  // --- Quick preset buttons ---
  document.querySelectorAll('[data-preset]').forEach(btn => {
    btn.addEventListener('click', () => {
      const presets = {
        'enhance': 'enhance the image, make it sharper and brighter, professional quality',
        'vintage': 'make it vintage film style, warm tones, slight grain, nostalgic look',
        'background': 'replace the background with a serene mountain landscape at sunset, keep the subject in focus',
        'anime': 'transform into anime style, cel shading, vibrant colors',
      };
      document.getElementById('prompt').value = presets[btn.dataset.preset] || '';
    });
  });

  // --- Mask preview ---
  maskUpload?.addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (file) {
      uploadedMaskFile = file;
      maskLabel.textContent = file.name;
    }
  });

  // --- File upload ---
  uploadInput.addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (!file) return;
    uploadedFile = file;
    uploadedFileUrl = URL.createObjectURL(file);
    previewImg.src = uploadedFileUrl;
    previewWrapper.classList.remove('hidden');
  });

  removeBtn?.addEventListener('click', function () {
    uploadedFile = null;
    uploadedFileUrl = null;
    uploadInput.value = '';
    previewWrapper.classList.add('hidden');
    previewImg.src = '';
  });

  // --- Add result to gallery ---
  function addToGallery(imageUrl, caption) {
    recentResults.unshift({ url: imageUrl, caption });
    if (recentResults.length > 8) recentResults.pop();

    // Re-render gallery
    resultGallery.innerHTML = '';
    if (recentResults.length === 0) {
      resultGallery.innerHTML = '<p class="text-xs text-slate-500 dark:text-slate-400 col-span-full">আপনার এডিটেড ছবি এখানে প্রদর্শিত হবে।</p>';
      return;
    }

    recentResults.forEach((r, i) => {
      const div = document.createElement('div');
      div.className = 'relative group';
      div.innerHTML = `
        <img src="${r.url}" alt="${r.caption || 'Result'}"
             class="gallery-thumb w-full rounded-lg border border-[rgb(var(--border))] cursor-pointer"
             onclick="window.open('${r.url}', '_blank')" />
        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center gap-2">
          <a href="${r.url}" download="ai-edited-${i}.png"
             class="p-1 bg-white/20 rounded hover:bg-white/30" title="Download">
            <i class="lucide lucide-download w-4 h-4 text-white" aria-hidden="true"></i>
          </a>
        </div>
      `;
      resultGallery.appendChild(div);
    });
  }

  // --- Remove Background ---
  removeBgBtn?.addEventListener('click', async function () {
    if (!uploadedFile) {
      showError('অনুগ্রহ করে প্রথমে একটি ছবি আপলোড করুন।');
      return;
    }
    const formData = new FormData();
    formData.append('image', uploadedFile);
    formData.append('size', document.getElementById('size').value);

    const originalHtml = removeBgBtn.innerHTML;
    removeBgBtn.disabled = true;
    removeBgBtn.innerHTML = '<i class="lucide lucide-loader-2 w-4 h-4 inline mr-2 animate-spin" aria-hidden="true"></i> প্রক্রিয়া করছে...';

    try {
      const resp = await fetch('{{ route('photo-edit.remove-bg') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      });
      const data = await resp.json();
      if (!data.ok) throw new Error(data.error || 'Background removal failed.');
      addToGallery(data.image_url || 'data:image/png;base64,' + data.image, 'Background removed');
    } catch (err) {
      showError(err.message);
    } finally {
      removeBgBtn.disabled = false;
      removeBgBtn.innerHTML = originalHtml;
    }
  });

  // --- AI Edit / Object Removal submit ---
  editForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!uploadedFile) {
      showError('অনুগ্রহ করে প্রথমে একটি ছবি আপলোড করুন।');
      return;
    }

    if (activeTool === 'edit') {
      const prompt = document.getElementById('prompt').value.trim();
      if (!prompt) {
        showError('একটি প্রম্পট লিখুন অথবা প্রিসেট ব্যবহার করুন।');
        return;
      }
    }

    if (activeTool === 'remove-object' && !uploadedMaskFile) {
      showError('অবজেক্ট রিমুভ করতে একটি মাস্ক আপলোড করুন।');
      return;
    }

    const formData = new FormData();
    formData.append('image', uploadedFile);
    formData.append('prompt', activeTool === 'edit'
        ? document.getElementById('prompt').value
        : 'remove the object marked in the mask, fill the area with surrounding content');
    formData.append('mode', document.getElementById('mode').value);
    formData.append('size', document.getElementById('size').value);
    if (uploadedMaskFile) formData.append('mask', uploadedMaskFile);

    const originalHtml = editBtn.innerHTML;
    editBtn.disabled = true;
    editBtnText.textContent = 'AI প্রক্রিয়া করছে...';

    try {
      const resp = await fetch('{{ route('photo-edit.edit') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      });
      const data = await resp.json();
      if (!data.ok) throw new Error(data.error || 'AI edit failed.');

      const imgUrl = data.content && data.content.startsWith('http')
          ? data.content
          : (data.image_url || 'data:image/png;base64,' + (data.content || ''));

      addToGallery(imgUrl, activeTool === 'edit' ? 'AI edited' : 'Object removed');
    } catch (err) {
      showError(err.message);
    } finally {
      editBtn.disabled = false;
      editBtn.innerHTML = originalHtml;
    }
  });

  // --- Inline error display ---
  function showError(message) {
    let toast = document.getElementById('errorToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'errorToast';
      toast.className = 'fixed top-4 right-4 z-50 max-w-md p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/40 shadow-lg toast-error';
      toast.innerHTML = '<p class="text-sm text-red-800 dark:text-red-200 flex items-center gap-2"><i class="lucide lucide-alert-circle w-4 h-4 flex-shrink-0"></i><span id="errorToastText"></span></p>';
      document.body.appendChild(toast);
    }
    document.getElementById('errorToastText').textContent = message;
    setTimeout(() => toast.remove(), 8000);
  }
});
</script>
@endpush
@endsection

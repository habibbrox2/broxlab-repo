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
</style>
@endpush

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">
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

  {{-- Not configured callout --}}
  @php
    $providerConfigured = config('scraper.cron_token'); // placeholder — real check below
  @endphp
  @if(!config('app.ai_enabled', false))
    <div class="mb-6 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40">
      <p class="text-sm text-amber-800 dark:text-amber-200">
        <i class="lucide lucide-alert-triangle w-4 h-4 inline mr-2" aria-hidden="true"></i>
        AI Photo Edit কাজ করতে একটি ওপেনএআই-সাপত্ত্বক সেবা প্রোভাইডার কনফিগার করুন।
        অ্যাডমিন প্যানেলে <a href="/admin/aisystem" class="underline font-medium">AI System → Providers</a> থেকে যোগ করুন।
      </p>
    </div>
  @endif

  {{-- Main editor card --}}
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
          <div>
            <label for="prompt" class="block text-sm font-medium text-[rgb(var(--text))] mb-2">
              কীভাবে এডিট করবেন? (Prompt)
            </label>
            <textarea id="prompt" name="prompt" rows="3"
                      placeholder="যেমন: remove the background, make it vintage, enhance the sky and make it sharper, change the background to a beach sunset..."
                      class="w-full px-4 py-3 rounded-xl bg-[rgb(var(--surface-soft))] border border-[rgb(var(--border))] text-[rgb(var(--text))] placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"></textarea>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
          </div>

          <template id="resultTemplate">
            <div class="mt-6 p-6 rounded-xl bg-[rgb(var(--surface-soft))] border border-[rgb(var(--border))]">
              <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-[rgb(var(--text))]">AI এডিটেড ছবি</h3>
                <a href="#" download="ai-edited-photo.png"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                  <i class="lucide lucide-download w-4 h-4" aria-hidden="true"></i>
                  Download
                </a>
              </div>
              <img class="w-full preview-container rounded-lg border border-[rgb(var(--border))]" alt="AI edited result" />
            </div>
          </template>

          <button type="submit" id="editBtn"
                  class="w-full py-3 px-6 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:shadow-indigo-500/40">
            <span id="editBtnText">AI দিয়ে এডিট করুন</span>
            <i class="lucide lucide-wand w-5 h-5 ml-2 inline" aria-hidden="true"></i>
          </button>
        </div>
      </form>
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
        এআই সেবা প্রদানকারীর সীমাবদ্ধতা ও মূল্য প্রয�োজ্য হতে পারে।
      </p>
    </div>
  </div>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const uploadInput = document.getElementById('imageUpload');
    const previewWrapper = document.getElementById('previewWrapper');
    const previewImg = document.getElementById('preview');
    const editForm = document.getElementById('editForm');
    const editBtn = document.getElementById('editBtn');
    const editBtnText = document.getElementById('editBtnText');
    const removeBtn = document.getElementById('removeImage');

    let uploadedFile = null;
    let uploadedFileUrl = null;

    // Handle file selection
    uploadInput.addEventListener('change', function (e) {
      const file = e.target.files[0];
      if (!file) return;
      uploadedFile = file;
      uploadedFileUrl = URL.createObjectURL(file);
      previewImg.src = uploadedFileUrl;
      previewWrapper.classList.remove('hidden');
      previewWrapper.querySelector('img').src = uploadedFileUrl;
    });

    // Remove image
    removeBtn?.addEventListener('click', function () {
      uploadedFile = null;
      uploadedFileUrl = null;
      uploadInput.value = '';
      previewWrapper.classList.add('hidden');
      previewImg.src = '';
    });

    // Handle form submit
    editForm.addEventListener('submit', async function (e) {
      e.preventDefault();

      if (!uploadedFile) {
        alert('অনুগ্রহ করে প্র�মে একটি ছবি আপলোড করুন।');
        return;
      }

      const formData = new FormData();
      formData.append('image', uploadedFile);
      formData.append('prompt', document.getElementById('prompt').value);
      formData.append('mode', document.getElementById('mode').value);
      formData.append('size', document.getElementById('size').value);

      // Show loading state
      editBtn.disabled = true;
      editBtnText.textContent = 'AI প্রক্রিয়া করছে...';

      try {
        const response = await fetch('{{ route('photo-edit.edit') }}', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: formData,
        });

        const data = await response.json();

        if (!data.ok) {
          throw new Error(data.error || 'AI edit failed.');
        }

        // Display result
        const existingResult = document.getElementById('aiResultCard');
        if (existingResult) existingResult.remove();

        const template = document.getElementById('resultTemplate');
        const clone = template.content.cloneNode(true);
        clone.getElementById('aiResultCard').id = 'aiResultCard';

        if (data.content && data.content.startsWith('http')) {
          clone.querySelector('img').src = data.content;
          clone.querySelector('a').href = data.content;
        } else if (data.content) {
          const img = clone.querySelector('img');
          img.src = 'data:image/png;base64,' + data.content;
          clone.querySelector('a').href = img.src;
        }

        editForm.parentNode.insertBefore(clone, editForm.nextSibling);
      } catch (err) {
        alert('ত্রুটি: ' + err.message);
      } finally {
        editBtn.disabled = false;
        editBtnText.textContent = 'AI দিয়ে এডিট করুন';
      }
    });
  });
</script>
@endpush
@endsection
const STATE={ templates: {},selected: null,csrf: '', };
function init(){
  STATE.templates=window.__mktTemplates||{};
  STATE.csrf=window.__mktCsrf||'';
  const search=document.getElementById('mkt-search');
  if (search)search.addEventListener('input',function (){filterTemplates(this.value,document.querySelector('.mkt-filter-btn.active')?.dataset?.filter||'all');});
  const filters=document.querySelectorAll('.mkt-filter-btn');
  filters.forEach((btn) =>{btn.addEventListener('click',function (){
    filters.forEach((b) =>{b.classList.remove('active');});
    this.classList.add('active');
    const searchVal=document.getElementById('mkt-search')?.value||'';
    filterTemplates(searchVal,this.dataset.filter);
  });});
}
function filterTemplates(search,category){
  const grid=document.getElementById('mkt-grid');
  const empty=document.getElementById('mkt-empty');
  if (!grid) return;
  const cards=grid.querySelectorAll('.mkt-card');
  let visible=0;
  cards.forEach((card) =>{
    const name=card.getAttribute('data-name')||'';
    const cat=card.getAttribute('data-category')||'';
    const matchesSearch=!search||name.includes(search.toLowerCase());
    const matchesCategory=category==='all'||cat===category;
    const show=matchesSearch&&matchesCategory;
    card.style.display=show?'':'none';
    if (show)visible++;
  });
  if (empty)empty.style.display=visible===0?'block':'none';
}
window.mktPreview=function (slug){
  const tmpl=STATE.templates[slug];
  if (!tmpl) return;
  const modal=document.getElementById('mkt-modal');
  const body=document.getElementById('mkt-modal-body');
  const preview=document.getElementById('mkt-modal-preview');
  if (!modal||!body||!preview) return;
  preview.style.background=tmpl.gradient;
  preview.innerHTML=`<i class="lucide lucide-${tmpl.icon}" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:6rem;color:rgba(255,255,255,0.4);"></i>`;
  body.innerHTML=`<div class="mkt-modal-name">${escHtml(tmpl.name)}</div>`+
`<div class="mkt-modal-category">${escHtml(tmpl.category)}</div>`+
`<div class="mkt-modal-desc">${escHtml(tmpl.description)}</div>`+
`<div class="mkt-modal-features"><h4>Features</h4><div class="mkt-modal-feature-list">${
  (tmpl.features||[]).map((f) =>{return `<div class="mkt-modal-feature-item"><i class="lucide lucide-check-circle" style="width:1em;height:1em;"></i> ${escHtml(f)}</div>`;}).join('')
}</div></div>`+
`<div class="mkt-modal-best"><strong>Best for:</strong> ${escHtml(tmpl.best_for||'All professionals')}</div>`+
'<div class="mkt-modal-actions">'+
'<button class="mkt-modal-btn mkt-modal-btn-secondary" onclick="window.mktCloseModal()">Close</button>'+
`<button class="mkt-modal-btn mkt-modal-btn-primary" onclick="window.mktSelectTemplate('${slug}')"><i class="lucide lucide-check-circle" style="width:1em;height:1em;"></i> Use This Template</button>`+
'</div>';
  modal.classList.add('open');
  document.body.style.overflow='hidden';
};
window.mktCloseModal=function (){
  const modal=document.getElementById('mkt-modal');
  if (modal)modal.classList.remove('open');
  document.body.style.overflow='';
};
window.mktSelectTemplate=function (slug){
  STATE.selected=slug;
  const form=document.createElement('form');
  form.method='POST';
  form.action='/cv-builder';
  const csrf=document.createElement('input');
  csrf.type='hidden';csrf.name='csrf_token';csrf.value=STATE.csrf;
  const tmpl=document.createElement('input');
  tmpl.type='hidden';tmpl.name='template';tmpl.value=slug;
  form.appendChild(csrf);
  form.appendChild(tmpl);
  document.body.appendChild(form);
  form.submit();
};
document.addEventListener('click',(e) =>{
  const modal=document.getElementById('mkt-modal');
  if (modal&&modal.classList.contains('open')&&e.target===modal){
    window.mktCloseModal();
  }
});
document.addEventListener('keydown',(e) =>{
  if (e.key==='Escape')window.mktCloseModal();
});
function escHtml(str){if (!str) return '';return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
if (document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);} else {init();}

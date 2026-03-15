function E(s={}){const o=s.containerSelector||"#linked-emails-container",l=s.formSelector||"#link-email-form",h=s.emailInputSelector||"#new-email",b=s.messageSelector||"#link-email-message",y=s.csrfTokenSelector||null,i=document.querySelector(o),c=document.querySelector(l),d=document.querySelector(h),S=document.querySelector(b);if(!i&&!c)return null;const m=()=>{const t=document.querySelector('meta[name="csrf-token"]')?.content||"";if(t)return t;if(!y)return"";const e=document.querySelector(y);return e?.value||e?.content||""},a=async()=>{if(i)try{const t=await fetch("/api/user/linked-emails",{method:"GET",headers:{"X-Requested-With":"XMLHttpRequest"}});if(!t.ok)throw new Error("Failed to load linked emails");const n=(await t.json())?.data||[];v(n)}catch(t){console.error("Error loading linked emails:",t),i.innerHTML=`
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Could not load linked emails. Please refresh the page.
                </div>
            `}},v=t=>{if(!i)return;if(!t||t.length===0){i.innerHTML=`
                <div class="alert alert-secondary">
                    <i class="bi bi-info-circle me-2"></i>
                    No additional emails linked yet. Add one below to strengthen your account security.
                </div>
            `;return}let e='<div class="linked-emails-list">';t.forEach(n=>{const r=n.email||n,g=n.is_primary||n.primary,u=n.verified!==!1;e+=`
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-envelope-fill text-primary"></i>
                                <strong>${f(r)}</strong>
                                ${g?'<span class="badge bg-success"><i class="bi bi-star-fill me-1"></i>Primary</span>':""}
                                ${u?"":'<span class="badge bg-warning"><i class="bi bi-clock me-1"></i>Pending</span>'}
                            </div>
                            <small class="text-muted">${u?"Verified":"Verification pending"}</small>
                        </div>
                        <div class="btn-group btn-group-sm gap-2" role="group">
                            ${!g&&u?`
                                <button type="button" class="btn btn-outline-primary js-set-primary" data-email="${f(r)}">
                                    <i class="bi bi-star me-1"></i> Set Primary
                                </button>
                            `:""}
                            <button type="button" class="btn btn-outline-danger js-unlink-email" data-email="${f(r)}">
                                <i class="bi bi-unlink me-1"></i> Unlink
                            </button>
                        </div>
                    </div>
                </div>
            `}),e+="</div>",i.innerHTML=e,i.querySelectorAll(".js-set-primary").forEach(n=>{n.addEventListener("click",()=>{const r=n.dataset.email;w(r)})}),i.querySelectorAll(".js-unlink-email").forEach(n=>{n.addEventListener("click",()=>{const r=n.dataset.email;confirm(`Are you sure you want to unlink ${r}?`)&&k(r)})})},p=async t=>{if(!t||!t.trim()){window.showAlert("Please enter an email address","warning");return}try{const e=await fetch("/api/user/linked-emails",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":m(),"X-Requested-With":"XMLHttpRequest"},body:JSON.stringify({email:t.trim()})}),n=await e.json();if(!e.ok)throw new Error(n?.message||"Failed to link email");window.showAlert("Email link request sent! Check your inbox for verification link.","success"),d&&(d.value=""),setTimeout(()=>a(),2e3)}catch(e){window.showAlert(e?.message||"An error occurred. Please try again.","danger")}},k=async t=>{try{const e=await fetch(`/api/user/linked-emails/${encodeURIComponent(t)}`,{method:"DELETE",headers:{"X-CSRF-Token":m(),"X-Requested-With":"XMLHttpRequest"}}),n=await e.json();if(!e.ok)throw new Error(n?.message||"Failed to unlink email");window.showAlert("Email unlinked successfully","success"),a()}catch(e){window.showAlert(e?.message||"An error occurred. Please try again.","danger")}},w=async t=>{try{const e=await fetch(`/api/user/linked-emails/${encodeURIComponent(t)}/primary`,{method:"PATCH",headers:{"Content-Type":"application/json","X-CSRF-Token":m(),"X-Requested-With":"XMLHttpRequest"},body:JSON.stringify({set_primary:!0})}),n=await e.json();if(!e.ok)throw new Error(n?.message||"Failed to set primary email");window.showAlert("Primary email updated successfully","success"),a()}catch(e){showError(e)}};return c&&c.addEventListener("submit",t=>{t.preventDefault();const e=d?.value||"";p(e)}),a(),{loadLinkedEmails:a,linkEmail:p,unlinkEmail:k,setPrimaryEmail:w}}function f(s){const o={"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"};return s.replace(/[&<>"']/g,l=>o[l])}var q=E;export{q as default,E as initLinkedEmails};

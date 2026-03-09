import{escapeHtml as g}from"./core.js";const e=C=>document.getElementById(C),V=()=>document.querySelector('meta[name="csrf-token"]')?.content||"";async function se(){try{const C=await import("/assets/firebase/v2/dist/notification-system.js"),$=await import("/assets/firebase/v2/dist/analytics.js");return{notificationSystem:C,analytics:$}}catch{return{notificationSystem:null,analytics:null}}}async function re(){const C=e("notificationForm");if(!C)return;const{notificationSystem:$,analytics:k}=await se(),w=$?.showWarning||window.showWarning||window.showMessage,H=$?.showSuccess||window.showSuccess||window.showMessage,I=$?.showError||window.showError||window.showMessage,N=k?.trackAdminNotificationSend,h=e("recipientType"),x=e("notificationTitle"),S=e("notificationMessage"),l=e("notificationType"),d=e("notificationTemplate"),f=e("templateVariables"),E=e("templateVariablesWrap"),T=e("applyTemplatePreviewBtn"),u=e("submitBtn"),c=i=>{if(!i||!String(i).trim())return{};try{const t=JSON.parse(i);return t&&typeof t=="object"&&!Array.isArray(t)?t:null}catch{return null}},n=i=>{if(!Array.isArray(i))return[];const t=[];return i.forEach(s=>{let a=String(s||"").trim().toLowerCase();a&&((a==="fcm"||a==="firebase")&&(a="push"),(a==="in-app"||a==="inapp")&&(a="in_app"),t.includes(a)||t.push(a))}),t};function r(){const i=!!d?.value;E&&(E.style.display=i?"block":"none"),x&&(x.required=!i),S&&(S.required=!i)}function o(i=!1){if(!d?.value)return{};const t=c(f?.value||"");return t===null?(i&&w?.("Template variables must be a valid JSON object"),null):t}function b(i=[]){const t=n(i);if(!t.length)return;const s=e("channelPush"),a=e("channelInApp"),m=e("channelEmail");s&&(s.checked=t.includes("push")),a&&(a.checked=t.includes("in_app")),m&&(m.checked=t.includes("email"))}async function p(){if(!d?.value)return!0;const i=d.selectedOptions?.[0],t=parseInt(i?.dataset.templateId||"0",10);if(!t)return!0;const s=o(!0);if(s===null)return!1;const a=new URLSearchParams;Object.keys(s).length>0&&a.set("vars",JSON.stringify(s));const m=`/admin/notification-templates/${t}/preview${a.toString()?`?${a.toString()}`:""}`;try{const y=await(await fetch(m)).json();if(!y?.success)throw new Error(y?.error||"Template preview failed");return x&&(x.value=y.title||"",x.dispatchEvent(new Event("input"))),S&&(S.value=y.body||"",S.dispatchEvent(new Event("input"))),b(y.channels||[]),!0}catch{return w?.("Template preview failed. Notification will use template during send."),!1}}function U(){const i=h?.value,t=e("recipientInfo");let s="";switch(i){case"all":s="This will send to all users and guest devices";break;case"guest":s="Only guest users (not logged in)";break;case"specific":s="Only the specific users you select will receive notifications";break;case"role":s="All users with the selected role will receive notifications";break;case"permission":s="All users with the selected permission will receive notifications";break}s&&t&&(t.innerHTML='<small class="text-success d-block mt-2"><i class="bi bi-check-circle"></i> '+s+"</small>")}async function D(){const i=h?.value;if(i){if(i==="specific"&&!Array.from(e("specificUsers")?.selectedOptions||[]).map(s=>parseInt(s.value,10)).filter(s=>!Number.isNaN(s)).length){e("recipientCount").textContent="0";const s=e("totalUsers");s&&(s.textContent="0");const a=e("guestUsers");a&&(a.textContent="0");return}if(i==="role"&&!e("roleSelect")?.value){e("recipientCount").textContent="0";const t=e("totalUsers");t&&(t.textContent="0");const s=e("guestUsers");s&&(s.textContent="0");return}if(i==="permission"&&!e("permissionSelect")?.value){e("recipientCount").textContent="0";const t=e("totalUsers");t&&(t.textContent="0");const s=e("guestUsers");s&&(s.textContent="0");return}try{const t=new URLSearchParams({type:i});if(i==="specific"){const y=Array.from(e("specificUsers")?.selectedOptions||[]).map(L=>parseInt(L.value,10)).filter(L=>!Number.isNaN(L));y.length&&t.set("ids",y.join(","))}if(i==="role"){const y=e("roleSelect")?.value;y&&t.set("role",y)}if(i==="permission"){const y=e("permissionSelect")?.value;y&&t.set("permission",y)}const a=await(await fetch("/api/notification/count-recipients?"+t.toString())).json();e("recipientCount").textContent=a.count;const m=e("totalUsers");m&&(m.textContent=a.count);const v=e("guestUsers");v&&(v.textContent=a.guest_count||0)}catch(t){console.error("Error:",t)}}}function F(){h?.value||(h.value="all"),e("specificUserDiv").style.display=h.value==="specific"?"block":"none",e("roleDiv").style.display=h.value==="role"?"block":"none",e("permissionDiv").style.display=h.value==="permission"?"block":"none",e("recipientPreview").style.display=h.value?"block":"none",u&&(u.disabled=!h.value),U(),D()}F(),r(),d?.addEventListener("change",async function(){r();const i=this.selectedOptions?.[0],t=c(i?.dataset.templateVars||"{}");if(f){const s=t&&typeof t=="object"?Object.keys(t):[],a={};s.forEach(m=>{a[m]=""}),f.value=s.length?JSON.stringify(a,null,2):"{}"}await p()}),T?.addEventListener("click",async()=>{await p()}),fetch("/api/notification/roles").then(i=>i.json()).then(i=>{const t=e("roleSelect");i.roles?.forEach(s=>{const a=document.createElement("option");a.value=s.name,a.textContent=s.name,t.appendChild(a)})}),fetch("/api/notification/permissions").then(i=>i.json()).then(i=>{const t=e("permissionSelect");i.permissions?.forEach(s=>{const a=document.createElement("option");a.value=s.name,a.textContent=s.name,t.appendChild(a)})}),h?.addEventListener("change",function(){e("specificUserDiv").style.display=this.value==="specific"?"block":"none",e("roleDiv").style.display=this.value==="role"?"block":"none",e("permissionDiv").style.display=this.value==="permission"?"block":"none",e("recipientPreview").style.display=this.value?"block":"none",u&&(u.disabled=!this.value),D(),U()}),e("specificUsers")?.addEventListener("change",D),e("roleSelect")?.addEventListener("change",D),e("permissionSelect")?.addEventListener("change",D),x?.addEventListener("input",function(){const i=this.value||"";e("previewTitle").textContent=i||"Notification Title",i.length>0&&i.length<=100?(this.classList.remove("is-invalid"),this.classList.add("is-valid")):i.length>100?(this.classList.remove("is-valid"),this.classList.add("is-invalid")):this.classList.remove("is-valid","is-invalid")}),S?.addEventListener("input",function(){const i=this.value||"";e("previewMessage").textContent=i||"Your notification message will appear here...";const t=i.length,s=e("wordCount");s&&(s.textContent=`${t} chars / ${i.split(/\s+/).filter(a=>a).length} words`,s.className="small fw-bold ",t>450?s.classList.add("text-danger"):t>350?s.classList.add("text-warning"):s.classList.add("text-muted")),t>0&&t<=500?(this.classList.remove("is-invalid"),this.classList.add("is-valid")):t>500?(this.classList.remove("is-valid"),this.classList.add("is-invalid")):this.classList.remove("is-valid","is-invalid")}),l?.addEventListener("change",function(){const i={general:"General",promotion:"Promotion",announcement:"Announcement",update:"Update",warning:"Warning",urgent:"Urgent"};e("previewType").textContent="Type: "+(i[this.value]||this.value)}),C.addEventListener("submit",async function(i){i.preventDefault();const t=[];if(e("channelPush")?.checked&&t.push("push"),e("channelInApp")?.checked&&t.push("in_app"),e("channelEmail")?.checked&&t.push("email"),t.length===0){w?.("Please select at least one delivery channel");return}const s=h?.value;let a=[];if(s==="specific"){const M=e("specificUsers")?.selectedOptions||[];if(a=Array.from(M).map(A=>parseInt(A.value,10)).filter(Boolean),a.length===0){w?.("Please select at least one specific user");return}}let m=0;s==="specific"?m=a.length:m=parseInt(e("recipientCount")?.textContent||"0",10)||0;const v=d?.value||"",y=o(!0);if(y===null)return;const L={recipient_type:s,specific_ids:a,role_name:e("roleSelect")?.value||"",permission_name:e("permissionSelect")?.value||"",title:x?.value||"",message:S?.value||"",template_slug:v,template_variables:y,type:l?.value||"general",action_url:e("actionUrl")?.value||"",channels:t,scheduled_at:e("scheduledTime")?.value||null,is_draft:!!e("saveDraft")?.checked,recipient_count:m};u&&(u.disabled=!0,u.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Sending...');try{const A=await(await fetch("/api/notification/send",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":V()},body:JSON.stringify(L)})).json();A.success?(N?.(L,L.recipient_count||0),H?.(A.message||"Notification sent successfully"),setTimeout(()=>window.location.href="/admin/notifications",1500)):(I?.("Error: "+(A.error||"Unknown error")),u&&(u.disabled=!1,u.innerHTML='<i class="bi bi-check me-2"></i>Send Notification'))}catch(M){console.error("Error:",M),I?.("Notification sending failed: "+M.message),u&&(u.disabled=!1,u.innerHTML='<i class="bi bi-check me-2"></i>Send Notification')}}),fetch("/api/notification/users").then(i=>i.json()).then(i=>{const t=e("specificUsers");t&&(t.innerHTML="",i.users?.forEach(s=>{const a=document.createElement("option");a.value=s.id,a.textContent=s.username+" ("+s.email+")",t.appendChild(a)}))});const B=e("recipientSearchInput"),R=e("recipientDeviceFilter"),q=e("recipientFilterReset"),X=e("recipientFilteredCount"),P=e("recipientFilterHint"),O=i=>String(i||"").trim().toLowerCase(),Z=i=>{const t=O(i);return t?t.includes("android")?"android":t.includes("iphone")||t.includes("ipad")||t.includes("ios")?"ios":t.includes("windows")||t.includes("mac")||t.includes("linux")||t.includes("desktop")?"desktop":(t.includes("web")||t.includes("browser"),"web"):"web"};function j(i,t){if(X&&(X.textContent=String(i)),!!P){if(t<=0){P.textContent="Preview recipients to use filters";return}i===t?P.textContent="Showing all recipients":i===0?P.textContent="No recipient matched current filters":P.textContent=`Showing ${i} of ${t}`}}function G(i,t){if(!i)return;let s=e("recipientFilterEmptyState");if(!t){s&&s.remove();return}s||(s=document.createElement("div"),s.id="recipientFilterEmptyState",s.className="recipient-empty-filter",s.innerHTML='<i class="bi bi-search me-1"></i>No recipients matched the selected filters',i.appendChild(s))}function z(){const i=e("recipientList");if(!i)return;const t=Array.from(i.querySelectorAll(".recipient-card"));if(!t.length){j(0,0),G(i,!1);return}const s=O(B?.value),a=O(R?.value);let m=0;t.forEach(v=>{const y=O(v.dataset.recipientName),L=O(v.dataset.recipientEmail),M=O(v.dataset.recipientDevice),A=O(v.dataset.recipientDeviceCategory),_=!s||y.includes(s)||L.includes(s),Q=!a||A===a||M.includes(a),J=_&&Q;v.classList.toggle("is-hidden",!J),J&&(m+=1)}),j(m,t.length),G(i,m===0)}B&&B.dataset.bound!=="1"&&(B.addEventListener("input",z),B.dataset.bound="1"),R&&R.dataset.bound!=="1"&&(R.addEventListener("change",z),R.dataset.bound="1"),q&&q.dataset.bound!=="1"&&(q.addEventListener("click",()=>{B&&(B.value=""),R&&(R.value=""),z()}),q.dataset.bound="1"),j(0,0),e("previewBtn")?.addEventListener("click",async function(){const i=h?.value,t=new URLSearchParams({type:i});if(i==="specific"){const a=Array.from(e("specificUsers")?.selectedOptions||[]).map(m=>parseInt(m.value,10)).filter(m=>!Number.isNaN(m));if(!a.length){w?.("\u09AC\u09BF\u09B6\u09C7\u09B7 \u09AC\u09CD\u09AF\u09AC\u09B9\u09BE\u09B0\u0995\u09BE\u09B0\u09C0 \u09A8\u09BF\u09B0\u09CD\u09AC\u09BE\u099A\u09A8 \u0995\u09B0\u09C1\u09A8");return}t.set("ids",a.join(","))}else if(i==="role"){const a=e("roleSelect")?.value;if(!a){w?.("\u098F\u0995\u099F\u09BF \u09AD\u09C2\u09AE\u09BF\u0995\u09BE \u09A8\u09BF\u09B0\u09CD\u09AC\u09BE\u099A\u09A8 \u0995\u09B0\u09C1\u09A8");return}t.set("role",a)}else if(i==="permission"){const a=e("permissionSelect")?.value;if(!a){w?.("\u098F\u0995\u099F\u09BF \u0985\u09A8\u09C1\u09AE\u09A4\u09BF \u09A8\u09BF\u09B0\u09CD\u09AC\u09BE\u099A\u09A8 \u0995\u09B0\u09C1\u09A8");return}t.set("permission",a)}const s=new bootstrap.Modal(e("recipientModal"));try{const m=await(await fetch("/api/notification/preview-recipients?"+t.toString())).text();let v;try{v=JSON.parse(m)}catch{throw new Error("Invalid response from server: "+m.substring(0,100))}const y=e("recipientList"),L=e("recipientTotalCount");if(y.innerHTML="",v.error)L&&(L.textContent="0"),y.innerHTML='<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-circle me-2"></i>Recipient load error: '+v.error+"</div>",j(0,0);else if(!v.recipients||v.recipients.length===0){L&&(L.textContent="0");const M=v.warning?`<div class="alert alert-warning mb-2"><i class="bi bi-exclamation-triangle me-2"></i>${v.warning}</div>`:"";y.innerHTML=M+'<div class="alert alert-info text-center mb-0"><i class="bi bi-info-circle me-2"></i>No recipients found for this selection</div>',j(0,0)}else{const M=v.count??v.recipients.length;if(L&&(L.textContent=String(M)),v.recipients.length<M){const _=document.createElement("div");_.className="text-end text-muted small mt-2",_.textContent=`Showing first ${v.recipients.length} of ${M}`,y.parentElement?.appendChild(_)}const A=v.recipients.map((_,Q)=>{const J=new Date(_.enabled_at),ee=J.toLocaleDateString("bn-BD",{year:"numeric",month:"2-digit",day:"2-digit"}),te=J.toLocaleTimeString("bn-BD",{hour:"2-digit",minute:"2-digit"}),Y=g(_.username||"Unknown"),W=g(_.email||""),K=g(_.device_info||"Web"),ne=String(_.device_info||"Web"),ie=g(Z(ne));return`
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="recipient-card h-100"
                                data-recipient-name="${Y}"
                                data-recipient-email="${W}"
                                data-recipient-device="${K}"
                                data-recipient-device-category="${ie}">
                                <div class="recipient-header">
                                    <div class="flex-grow-1">
                                        <div class="text-truncate">
                                            <strong class="d-block text-dark text-truncate">${Y}</strong>
                                            ${W?`<small class="text-muted text-truncate">${W}</small>`:""}
                                        </div>
                                    </div>
                                    <span class="badge bg-primary ms-2">${Q+1}</span>
                                </div>
                                <div class="recipient-body">
                                    <div class="recipient-info-item">
                                        <div class="recipient-info-label">
                                            <i class="bi bi-device-type"></i>
                                            Device
                                        </div>
                                        <div class="flex-grow-1 text-end">
                                            <small class="badge bg-light text-dark">${K}</small>
                                        </div>
                                    </div>
                                    <div class="recipient-info-item">
                                        <div class="recipient-info-label">
                                            <i class="bi bi-calendar-event"></i>
                                            Date
                                        </div>
                                        <div class="flex-grow-1 text-end">
                                            <small class="text-muted">${ee}</small>
                                        </div>
                                    </div>
                                    <div class="recipient-info-item">
                                        <div class="recipient-info-label">
                                            <i class="bi bi-clock"></i>
                                            Time
                                        </div>
                                        <div class="flex-grow-1 text-end">
                                            <small class="text-muted">${te}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `}).join("");y.innerHTML=`<div class="row g-3">${A}</div>`,z()}}catch(a){console.error("Fetch error:",a);const m=e("recipientTotalCount");m&&(m.textContent="0"),j(0,0),e("recipientList").innerHTML='<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Error: '+a.message+"</div>"}s.show()})}async function ce(){if(!e("scheduledNotificationsRoot"))return;let C=null;try{const l=await import("/assets/firebase/v2/dist/scheduled-notifications.js"),d=l.ScheduledNotifications||l.default;d&&(C=new d)}catch{return}const $=e("scheduleModal"),k=$?new bootstrap.Modal($):null;function w(){const l=e("scheduleForm");l&&l.reset(),H(),k?.show()}function H(){const l=e("recipientType")?.value,d=e("recipientIdsDiv");d&&(d.style.display=l==="user"?"block":"none")}async function I(l){l.preventDefault();const d=e("submitBtn");d&&(d.disabled=!0,d.innerHTML='<i class="bi bi-hourglass-split me-2"></i>Scheduling...');try{const f=e("scheduledDate")?.value,E=e("scheduledTime")?.value,T=`${f}T${E}:00`,u=[];document.querySelectorAll('input[id^="channel-"]:checked').forEach(o=>{u.push(o.value)});const c=e("recipientType")?.value;let n=[];c==="user"&&(n=(e("recipientIds")?.value||"").split(",").map(b=>parseInt(b.trim(),10)).filter(b=>!Number.isNaN(b)));const r=await C.scheduleNotification({title:e("notifTitle")?.value||"",body:e("notifBody")?.value||"",scheduled_at:T,user_timezone:e("userTimezone")?.value||"Asia/Dhaka",recipient_type:c,recipient_ids:n,channels:u});r?.success?(alert("Notification scheduled successfully."),k?.hide(),h("scheduled")):alert("Failed to schedule notification: "+(r?.error||"Unknown error"))}catch(f){console.error("Error:",f),alert("Server error: "+f.message)}finally{d&&(d.disabled=!1,d.innerHTML='<i class="bi bi-check-lg me-2"></i>Schedule')}}function N(l){return{scheduled:'<span class="badge bg-info">Scheduled</span>',sending:'<span class="badge bg-warning">Sending</span>',sent:'<span class="badge bg-success">Sent</span>',failed:'<span class="badge bg-danger">Failed</span>',cancelled:'<span class="badge bg-secondary">Cancelled</span>',draft:'<span class="badge bg-secondary">Draft</span>'}[l]||'<span class="badge bg-secondary">Unknown</span>'}async function h(l="scheduled"){try{const f=await(await fetch(`/api/notification/list-scheduled?status=${encodeURIComponent(l)}&limit=50`)).json(),E=e(`${l}-list`);if(!E)return;E.innerHTML="";const T=f&&(f.scheduled||f.notifications)||[];if(!f?.success||!Array.isArray(T)||T.length===0){E.innerHTML=`
                    <div class="col-12">
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox display-4 mb-3 d-block"></i>
                            <p>No notifications found.</p>
                        </div>
                    </div>
                `;return}T.forEach(u=>{const c=N(u.status);E.innerHTML+=`
                    <div class="col-lg-6 mb-4">
                        <div class="admin-panel-card h-100">
                            <div class="admin-panel-card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h5 class="mb-0">${g(u.title)}</h5>
                                        <small class="text-muted">${g(u.created_at||"-")}</small>
                                    </div>
                                    ${c}
                                </div>

                                <p class="text-muted mb-3">${g(u.body||u.message||"")}</p>

                                <div class="mb-3">
                                    <div class="mb-2">
                                        <small class="text-muted"><i class="bi bi-calendar me-1"></i>Scheduled:</small>
                                        <div class="fw-bold">${g(u.scheduled_at||"-")}</div>
                                    </div>
                                    <div>
                                        <small class="text-muted"><i class="bi bi-people me-1"></i>Recipient:</small>
                                        <span class="badge bg-info text-capitalize">${g(u.recipient_type||"all")}</span>
                                    </div>
                                </div>

                                <div class="mt-auto pt-2 border-top">
                                    <div class="btn-group btn-group-sm w-100" role="group">
                                        <button class="btn btn-outline-primary" data-action="view-scheduled" data-notification-id="${u.id}">
                                            <i class="bi bi-eye me-1"></i>Details
                                        </button>
                                        ${u.status==="scheduled"?`
                                            <button class="btn btn-outline-danger" data-action="cancel-scheduled" data-notification-id="${u.id}">
                                                <i class="bi bi-x me-1"></i>Cancel
                                            </button>
                                        `:""}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `})}catch(d){console.error("Error:",d);const f=e(`${l}-list`);f&&(f.innerHTML=`
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>Error: ${g(d.message||"Failed to load")}
                        </div>
                    </div>
                `)}}async function x(l){if(confirm("Cancel this scheduled notification?"))try{const f=await(await fetch(`/api/notification/scheduled/${l}`,{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-Token":V()}})).json();f.success?(alert("Schedule cancelled."),h("scheduled")):alert("Failed: "+(f.message||f.error||"Unknown error"))}catch(d){console.error("Error:",d),alert("Server error while cancelling.")}}function S(l){alert("Detailed view is coming soon. ID: "+l)}document.addEventListener("click",l=>{if(l.target.closest?.('[data-action="open-schedule-modal"]')){w();return}const f=l.target.closest?.('[data-action="view-scheduled"]');if(f){const T=parseInt(f.dataset.notificationId,10);Number.isNaN(T)||S(T);return}const E=l.target.closest?.('[data-action="cancel-scheduled"]');if(E){const T=parseInt(E.dataset.notificationId,10);Number.isNaN(T)||x(T)}}),e("scheduleForm")?.addEventListener("submit",I),e("recipientType")?.addEventListener("change",H),H(),h("scheduled"),e("scheduled-tab")?.addEventListener("shown.bs.tab",()=>h("scheduled")),e("sent-tab")?.addEventListener("shown.bs.tab",()=>h("sent")),e("failed-tab")?.addEventListener("shown.bs.tab",()=>h("failed")),e("draft-tab")?.addEventListener("shown.bs.tab",()=>h("draft"))}async function oe(){if(!e("deviceSyncRoot"))return;let $=null;function k(){const c="__fcm_device_id";try{const n=localStorage.getItem(c);if(n)return n;const r=`${Date.now()}-${Math.random().toString(36).slice(2,11)}`;return localStorage.setItem(c,r),r}catch{return`admin-${Date.now()}`}}function w(c){const n=String(c||"");return n?`${n.substring(0,8)}...`:"N/A"}function H(c){return{read:'<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Read</span>',dismissed:'<span class="badge bg-warning"><i class="bi bi-x-circle me-1"></i>Dismissed</span>',deleted:'<span class="badge bg-danger"><i class="bi bi-trash me-1"></i>Deleted</span>',sync:'<span class="badge bg-info"><i class="bi bi-arrow-repeat me-1"></i>Sync</span>'}[c]||`<span class="badge bg-secondary">${g(c||"unknown")}</span>`}function I(c){const n=String(c?.status||"").toLowerCase();return n==="sent"||n==="success"||n==="synced"||c?.synced_at?'<span class="badge bg-success">Synced</span>':n==="failed"||n==="error"?'<span class="badge bg-danger">Failed</span>':'<span class="badge bg-warning">Pending</span>'}async function N(c){const r=(await c.text()).replace(/^\uFEFF/,"").trim();try{return JSON.parse(r||"{}")}catch{throw new Error(`Invalid JSON response (${c.status}): ${r.slice(0,160)}`)}}function h(c){try{const n=typeof c.count=="number"?c.count:Array.isArray(c.devices)?c.devices.length:0;e("activeDevicesCount").textContent=n,e("pendingSyncCount").textContent=c.pending_count||0,e("syncedItemsCount").textContent=c.synced_count||0;const r=(c.pending_count||0)+(c.synced_count||0),o=r>0?Math.round((c.synced_count||0)/r*100):0;e("syncRatePercent").textContent=o+"%"}catch(n){console.error("Error updating stats:",n)}}async function x(){try{const c=await fetch("/api/notification/device-list"),n=await N(c);if(!n.success)throw new Error(n.error||n.message||"Failed to load device list");const r=e("devicesTableBody");if(!r)return;if(r.innerHTML="",!Array.isArray(n.devices)||n.devices.length===0){r.innerHTML=`
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="bi bi-inbox"></i> No devices found
                        </td>
                    </tr>
                `,h(n);return}n.devices.forEach(o=>{const b=String(o.device_id||""),p=o.device_name||o.username||"Unknown Device",U=o.device_type||o.platform||"web",D=o.last_active||o.last_sync||o.created_at||null,F=D?new Date(D).toLocaleString("bn-BD"):"N/A";r.innerHTML+=`
                    <tr>
                        <td>
                            <i class="bi bi-phone me-2"></i>
                            ${g(p)}
                        </td>
                        <td><code>${g(w(b))}</code></td>
                        <td><span class="badge bg-light text-dark">${g(U)}</span></td>
                        <td><small class="text-muted">${g(F)}</small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="syncDevice('${g(b)}')">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="removeDevice('${g(b)}')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `}),h(n)}catch(c){console.error("Error:",c);const n=e("devicesTableBody");n&&(n.innerHTML=`
                    <tr>
                        <td colspan="5" class="text-center text-danger py-4">
                            <i class="bi bi-exclamation-triangle me-2"></i>${g(c.message||"Failed")}
                        </td>
                    </tr>
                `)}}async function S(c="all"){try{const n=await fetch(`/api/notification/sync-log?action=${c!=="all"?encodeURIComponent(c):""}`),r=await N(n),o=e("syncLogBody");if(!o)return;if(o.innerHTML="",!r.success||!Array.isArray(r.logs)||r.logs.length===0){o.innerHTML=`
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="bi bi-inbox"></i> No sync logs
                        </td>
                    </tr>
                `;return}r.logs.forEach(b=>{const p=H(b.action),U=I(b),D=b.synced_at||b.created_at||null,F=D?new Date(D).toLocaleString("bn-BD"):"N/A";o.innerHTML+=`
                    <tr>
                        <td><small>${g(F)}</small></td>
                        <td>${p}</td>
                        <td><code>${g(b.notification_id??"-")}</code></td>
                        <td><code>${g(w(b.device_id||""))}</code></td>
                        <td>${U}</td>
                    </tr>
                `})}catch(n){console.error("Error:",n)}}async function l(){try{const c=await fetch("/api/notification/sync-status",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":V()},body:JSON.stringify({device_id:k(),device_type:"web",action:"sync"})}),n=await N(c);n.success?(alert("Sync completed successfully"),S(),x()):alert("Sync failed: "+(n.error||n.message||"Unknown error"))}catch(c){console.error("Error:",c),alert("Server error while syncing")}}async function d(c){try{const n=await fetch("/api/notification/sync-status",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":V()},body:JSON.stringify({device_id:String(c||""),device_type:"web",action:"sync"})}),r=await N(n);r.success?(alert("Device synced successfully"),x(),S()):alert("Device sync failed: "+(r.error||r.message||"Unknown error"))}catch(n){console.error("Error:",n),alert("Server error while syncing device")}}async function f(c){if(confirm("Are you sure you want to remove this device?"))try{const n=await fetch(`/api/notification/devices/${encodeURIComponent(c)}`,{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-Token":V()}}),r=await N(n);r.success?(alert("Device removed successfully"),x(),S()):alert("Failed to remove device: "+(r.error||r.message||"Unknown error"))}catch(n){console.error("Error:",n),alert("Server error while removing device")}}async function E(){alert("Clear log API is not configured in backend."),S()}function T(c){S(c)}function u(){x()}window.refreshDeviceList=u,window.manualSync=l,window.clearSyncLog=E,window.filterSyncLog=T,window.syncDevice=d,window.removeDevice=f,e("autoSyncToggle")?.addEventListener("change",function(){this.checked?(l(),$=setInterval(l,3e4)):clearInterval($)}),x(),S(),e("autoSyncToggle")?.checked&&($=setInterval(l,3e4))}async function le(){if(e("offlineHandlerRoot"))try{let H=function(){if(w)return;const n=e("offlineModal");n&&typeof bootstrap<"u"&&bootstrap.Modal&&(w=new bootstrap.Modal(n))},f=function(){w||H(),w?w.show():alert("Offline simulation modal is not available")},E=function(){const n=document.querySelector('input[name="offlineMode"]:checked')?.value;alert(`Offline simulation mode set to: ${n}. Verify behavior in DevTools network panel.`),w?.hide()},T=function(n){N(n)},c=function(){if(typeof bootstrap>"u"){setTimeout(c,100);return}H(),I(),N(),u("all")};const C=await import("/assets/firebase/v2/dist/offline-handler.js"),$=C.OfflineNotificationHandler||C.default;if(!$)return;const k=new $;let w=null;async function I(){try{const n=await k?.getBufferedNotifications?.()||[],r=e("bufferedTable");if(r.innerHTML="",n.length===0){r.innerHTML=`
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox"></i> No buffered notifications found
                            </td>
                        </tr>
                    `;return}n.forEach(o=>{const b=new Date(o.savedAt).toLocaleString("bn-BD"),p=`
                        <tr>
                            <td><code>${o.id.substring(0,8)}...</code></td>
                            <td>${g(o.title)}</td>
                            <td><small>${b}</small></td>
                            <td><span class="badge bg-info">Buffered</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" data-action="remove-buffer" data-notification-id="${o.id}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;r.innerHTML+=p}),e("bufferCount").textContent=n.length}catch(n){console.error("Error:",n)}}async function N(n="all"){try{const r=await k?.getRetryQueue?.()||[],o=e("retryQueueTable");o.innerHTML="";const b=n==="all"?r:r.filter(p=>n==="pending"?p.status==="pending":n==="failed"?p.status==="failed":!0);if(b.length===0){o.innerHTML=`
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox"></i> No retry queue items
                            </td>
                        </tr>
                    `;return}b.forEach(p=>{const U=new Date(p.nextRetryTime).toLocaleString("bn-BD"),D=p.status==="pending"?'<span class="badge bg-warning">Pending</span>':'<span class="badge bg-danger">Failed</span>',F=`
                        <tr>
                            <td><code>${p.id.substring(0,8)}...</code></td>
                            <td>${p.notificationId.substring(0,8)}...</td>
                            <td>${p.retryCount}</td>
                            <td><small>${U}</small></td>
                            <td>${D}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-action="force-retry" data-retry-id="${p.id}">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </td>
                        </tr>
                    `;o.innerHTML+=F}),e("retryCount").textContent=b.length}catch(r){console.error("Error:",r)}}async function h(n){try{await k?.removeFromBuffer?.(n),I()}catch(r){console.error("Error removing from buffer:",r)}}async function x(n){try{await k?.forceRetry?.(n),N()}catch(r){console.error("Error forcing retry:",r)}}async function S(){try{await k?.processQueue?.(),alert("Queue processed successfully"),I(),N()}catch(n){alert("Error: "+n.message)}}async function l(){try{await k?.clearExpiredCache?.(),alert("Expired cache cleared successfully"),I()}catch(n){alert("Error: "+n.message)}}async function d(){if(confirm("Do you want to clear all buffered notifications? This action cannot be undone."))try{await k?.clearCache?.(),alert("All buffered notifications were cleared"),I()}catch(n){alert("Error: "+n.message)}}async function u(n){try{const r=await k?.getDeliveryHistory?.()||[],o=e("deliveryHistoryTable");o.innerHTML="";const b=n==="all"?r:r.filter(p=>n==="success"?p.status==="success":n==="failed"?p.status==="failed":!0);if(b.length===0){o.innerHTML=`
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox"></i> No delivery history records
                            </td>
                        </tr>
                    `;return}b.forEach(p=>{const U=new Date(p.timestamp).toLocaleString("bn-BD"),D=p.status==="success"?'<span class="badge bg-success">Success</span>':'<span class="badge bg-danger">Failed</span>',F=`
                        <tr>
                            <td><small>${U}</small></td>
                            <td><code>${p.notificationId.substring(0,8)}...</code></td>
                            <td>${p.retryCount}</td>
                            <td>${D}</td>
                            <td>
                                <small class="text-muted">${g(p.error||"N/A")}</small>
                            </td>
                        </tr>
                    `;o.innerHTML+=F})}catch(r){console.error("Error:",r)}}document.addEventListener("click",n=>{const r=n.target.closest?.("[data-action]");if(!r)return;const o=r.dataset.action;if(o==="refresh-buffer")return I();if(o==="clear-expired-cache")return l();if(o==="process-queue")return S();if(o==="simulate-offline")return f();if(o==="clear-all-buffer")return d();if(o==="remove-buffer")return h(r.dataset.notificationId);if(o==="force-retry")return x(r.dataset.retryId);if(o==="filter-retry-queue")return T(r.dataset.filter||"all");if(o==="filter-history")return u(r.dataset.filter||"all");if(o==="apply-offline-mode")return E()}),document.readyState==="loading"?document.addEventListener("DOMContentLoaded",c):c()}catch{}}export{oe as initNotificationsDeviceSync,le as initNotificationsOfflineHandler,ce as initNotificationsScheduled,re as initNotificationsSend};

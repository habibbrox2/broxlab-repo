import{escapeHtml as o,setText as $,toSafeId as A}from"./core.js";const S=i=>document.getElementById(i);function N(i,n){let c=null;return(...d)=>{clearTimeout(c),c=setTimeout(()=>i(...d),n)}}function U(i){document.querySelectorAll(i).forEach(n=>{n.closest(".form-check")?.classList.toggle("checked",n.checked),n.addEventListener("change",function(){this.closest(".form-check")?.classList.toggle("checked",this.checked)})})}function L(i){return String(i||"").toLowerCase()==="active"?"bg-success":"bg-warning"}function I(i){const n=new Date(i);return Number.isNaN(n.getTime())?String(i||"-"):n.toLocaleDateString()}function T(){document.querySelector(".permission-checkbox")&&(window.selectAll=function(){document.querySelectorAll(".permission-checkbox").forEach(i=>{i.checked=!0,i.closest(".form-check")?.classList.add("checked")})},window.deselectAll=function(){document.querySelectorAll(".permission-checkbox").forEach(i=>{i.checked=!1,i.closest(".form-check")?.classList.remove("checked")})},U(".permission-checkbox"))}function j(i={}){const n=i.byId||S,c=n("userSearch");if(!c||c.dataset.rbacUserRolesBound==="1")return;c.dataset.rbacUserRolesBound="1";let d=null;const r=n("userResults"),p=n("userPanel"),f=new Map;function g(t){if(r){if(!Array.isArray(t)||t.length===0){f.clear(),r.innerHTML='<div class="alert alert-info">No users found.</div>',r.style.display="block";return}f.clear(),r.innerHTML=t.map(e=>{const s=String(e?.id??"").trim();if(!s)return"";const a=String(e?.first_name||"").trim(),l=String(e?.last_name||"").trim(),u=`${a} ${l}`.trim()||String(e?.username||"Unknown"),m=String(e?.username||""),v=String(e?.email||""),x=String(e?.created_at||""),w=String(e?.status||"unknown");return f.set(s,{id:Number(e?.id)||s,username:m,email:v,name:u,created:x,status:w}),`
                <button type="button"
                    class="list-group-item list-group-item-action cursor-pointer text-start"
                    data-user-id="${o(s)}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${o(u)}</h6>
                            <small class="text-muted">${o(m)} (${o(v)})</small>
                        </div>
                        <span class="badge ${L(w)}">${o(w)}</span>
                    </div>
                </button>
            `}).join(""),r.style.display="block"}}function b(){const t=c.value.trim();if(t.length<2){r&&(r.style.display="none");return}fetch(`/api/users/search?q=${encodeURIComponent(t)}`).then(e=>e.json()).then(e=>{g(Array.isArray(e?.data)?e.data:[])}).catch(()=>{r&&(r.innerHTML='<div class="alert alert-danger">Failed to search users.</div>',r.style.display="block")})}function y(t){fetch(`/api/user-roles/${t}`).then(e=>e.json()).then(e=>{const s=n("rolesList");if(!s)return;const a=Array.isArray(e?.data)?e.data:[];if(a.length===0){s.innerHTML='<div class="alert alert-info mb-0">No roles assigned.</div>';return}s.innerHTML=a.map(l=>`
                    <div class="role-badge">
                        <span>${o(l?.name||"Unnamed role")}</span>
                        <span
                            class="remove-btn"
                            data-role-id="${o(l?.id)}"
                            data-user-id="${o(t)}"
                            aria-label="Remove role">
                            &times;
                        </span>
                    </div>
                `).join("")+'<div class="clear-float"></div>'})}function k(t){fetch(`/api/user-roles/${t}`).then(e=>e.json()).then(()=>fetch("/api/rbac/permissions/grouped")).then(e=>e.json()).then(e=>{const s=n("permissionsList");if(!s)return;let a="";for(const[l,u]of Object.entries(e?.data||{}))a+=`<h6 class="fw-bold text-primary mb-2 mt-3 module-header">${o(l).toUpperCase()}</h6>`,a+='<div class="permissions-grid">',(u||[]).forEach(m=>{a+=`
                            <div class="permission-card">
                                <div class="permission-module">${o(m?.module||"")}</div>
                                <div class="permission-name">${o(m?.name||"")}</div>
                                <div class="permission-desc">${o(m?.description||"N/A")}</div>
                            </div>
                        `}),a+="</div>";s.innerHTML=a||'<div class="alert alert-info">No permissions found.</div>'})}window.selectUser=function(t,e,s,a,l,u){d=t,$(n("selectedUserName"),a),$(n("selectedUsername"),e),$(n("selectedUserEmail"),s),$(n("selectedUserCreated"),I(l));const m=n("selectedUserStatus");if(m){m.innerHTML="";const v=document.createElement("span");v.className=`badge ${L(u)}`,v.textContent=String(u||"unknown"),m.appendChild(v)}r&&(r.style.display="none"),p&&(p.style.display="block"),y(t),k(t)},window.removeUserRole=function(t,e){confirm("Remove this role from the user?")&&fetch(`/api/user-roles/${t}/remove/${e}`,{method:"POST"}).then(s=>s.json()).then(s=>{if(s?.success){y(t),k(t);return}alert("Error: "+(s?.error||"Unknown error"))})};const h=n("rolesList");h&&h.dataset.roleRemoveBound!=="1"&&(h.dataset.roleRemoveBound="1",h.addEventListener("click",t=>{const e=t.target.closest(".remove-btn[data-role-id][data-user-id]");e&&window.removeUserRole(e.dataset.userId,e.dataset.roleId)}));function R(){if(!d){alert("Please select a user first");return}fetch("/api/rbac/roles").then(t=>t.json()).then(t=>{const e=n("availableRolesCheckboxes");if(!e)return;e.innerHTML=(t?.data||[]).map(a=>{const l=String(a?.id||""),u=A(`role_${l}`)||`role_${l}`;return`
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="${o(l)}" id="${o(u)}">
                            <label class="form-check-label" for="${o(u)}">
                                ${o(a?.name||"Unnamed role")}
                                <small class="text-muted d-block">${o(a?.description||"")}</small>
                            </label>
                        </div>
                    `}).join(""),new bootstrap.Modal(n("assignRoleModal")).show()})}function E(){const t=Array.from(document.querySelectorAll("#availableRolesCheckboxes input:checked")).map(s=>s.value);if(t.length===0){alert("Please select at least one role");return}const e=new FormData;t.forEach(s=>e.append("roles[]",s)),fetch(`/api/user-roles/${d}/assign-roles`,{method:"POST",body:e}).then(s=>s.json()).then(s=>{if(s?.success){bootstrap.Modal.getInstance(n("assignRoleModal"))?.hide(),y(d),k(d),alert("Roles assigned successfully!");return}alert("Error: "+(s?.error||"Unknown error"))})}r&&r.dataset.userSelectBound!=="1"&&(r.dataset.userSelectBound="1",r.addEventListener("click",t=>{const e=t.target.closest("[data-user-id]");if(!e)return;const s=f.get(String(e.dataset.userId||""));s&&window.selectUser(s.id,s.username,s.email,s.name,s.created,s.status)})),n("assignRoleBtn")?.addEventListener("click",R),n("confirmAssignBtn")?.addEventListener("click",E),n("clearUserBtn")?.addEventListener("click",()=>{d=null,p&&(p.style.display="none"),r&&(r.style.display="none")}),c.addEventListener("keyup",N(b,300))}function B(){document.querySelector(".role-checkboxes")&&U(".role-checkboxes .form-check input")}function C(i={}){const n=i.byId||S,c=n("user-edit-data");if(!c)return;const d=parseInt(c.dataset.userId||"0",10);U(".role-checkboxes .form-check input");const r=n("userPermissions");if(!r||!d)return;function p(){fetch(`/api/user-roles/${d}`).then(f=>f.json()).then(f=>{if(!f?.data||f.data.length===0){r.innerHTML='<div class="alert alert-info mb-0">No roles assigned, no permissions available.</div>';return}return fetch("/api/rbac/permissions/grouped").then(g=>g.json()).then(g=>{let b="";for(const[y,k]of Object.entries(g?.data||{}))b+=`<div class="mb-3"><strong class="text-primary text-uppercase-9">${o(y)}</strong></div>`,(k||[]).forEach(h=>{b+=`<div class="permission-badge">
                                    <div class="module">${o(h?.module||"")}</div>
                                    <div>${o(h?.name||"")}</div>
                                    <small class="text-muted">${o(h?.description||"")}</small>
                                </div>`});r.innerHTML=b||'<div class="alert alert-info mb-0">No permissions found.</div>'})})}p()}export{T as initRbacRolesEdit,j as initRbacUserRoles,B as initUsersAddUser,C as initUsersEditUser};

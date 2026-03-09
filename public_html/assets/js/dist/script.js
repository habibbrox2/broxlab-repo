import"./shared/logout-runtime.js";const T=()=>document.querySelector('meta[name="user-id"]')?.content||null,b=t=>{document.readyState==="loading"?document.addEventListener("DOMContentLoaded",t,{once:!0}):t()},ht=/\/(login|register|forgot-password|reset-password|verify-2fa)/.test(window.location.pathname),G=!!T(),A=G?"user":"public",yt=window.__APP_JS_CONFIG?.notifications||window.__APP_CONFIG?.notifications||{},O="brox:navbar-dropdown-open",_t="brox:navbar-dropdown-close",q=new Map,D=new Map,N="notificationPermissionPopup",K="notificationPermissionPopupStyles",Q="__notification_perm_requested",X="__notification_perm_dismissed",R="global";function vt(){return document.querySelector('meta[name="csrf-token"]')?.content||""}function xt(t,e="default"){if(typeof window>"u")return;const n=!!t;window.__fcmMessagingSupported=n;try{window.dispatchEvent(new CustomEvent("fcm-support-resolved",{detail:{supported:n,context:e}}))}catch{}}function g(t){return String(t??"").replace(/[&<>"']/g,e=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"})[e]||e)}function J(t){const e=String(t||"").trim();return e&&(e.startsWith("/")||/^https?:\/\//i.test(e))?e:"#"}function St(t){if(!t)return"";const e=new Date(t);return Number.isNaN(e.getTime())?"":e.toLocaleString()}function L(t,e){if(t){const n=document.querySelector(t);if(n)return n}return e?document.querySelector(`[${e}]`):null}function Nt(t,e){const n=t?.closest("[data-notification-menu]");if(n){const i=n.querySelector("[data-notification-dropdown]");if(i)return i}return e?.closest(".brox-notification-dropdown")||null}function k(t){t&&(t.style.removeProperty("position"),t.style.removeProperty("left"),t.style.removeProperty("top"),t.style.removeProperty("right"),t.style.removeProperty("bottom"),t.style.removeProperty("inset"),t.style.removeProperty("transform"),t.style.removeProperty("z-index"))}function z(t,e){if(!t||!e)return;if(window.getComputedStyle(t).position==="static"){k(t);return}const i=8,r=window.innerWidth||document.documentElement.clientWidth,a=window.innerHeight||document.documentElement.clientHeight,o=t.getBoundingClientRect();if(!(o.left<i||o.right>r-i||o.top<i||o.bottom>a-i)){k(t);return}const d=e.getBoundingClientRect(),l=Math.min(o.width||t.offsetWidth||320,Math.max(180,r-i*2)),c=Math.min(o.height||t.offsetHeight||360,Math.max(160,a-i*2));let f=d.right-l;f=Math.max(i,Math.min(f,r-l-i));let u=d.bottom+8;if(u+c>a-i){const m=d.top-c-8;u=m>=i?m:Math.max(i,a-c-i)}t.style.position="fixed",t.style.left=`${Math.round(f)}px`,t.style.top=`${Math.round(u)}px`,t.style.right="auto",t.style.bottom="auto",t.style.inset="auto",t.style.transform="none",t.style.zIndex="1080"}function B(t){const e=String(t||"").trim().toLowerCase();return e&&e.replace(/[^a-z0-9_-]/g,"")||R}function Z(t={}){const e=t.permissionScope??t.scope;return B(e||t.context)}function P(t,e){const n=B(e);return n===R?t:`${t}__${n}`}function tt(t){try{return localStorage.getItem(t)==="true"}catch{return!1}}function $(t,e){try{e?localStorage.setItem(t,"true"):localStorage.removeItem(t)}catch{}}function Lt(){if(document.getElementById(K))return;const t=document.createElement("style");t.id=K,t.textContent=`
        .notification-permission-popup {
            position: fixed;
            right: 16px;
            bottom: calc(16px + env(safe-area-inset-bottom, 0px));
            width: min(400px, calc(100vw - 24px));
            z-index: 1055;
            border-radius: 18px;
            padding: 18px;
            color: #0f172a;
            border: 1px solid rgba(148, 163, 184, 0.28);
            background:
                linear-gradient(165deg, rgba(255, 255, 255, 0.94), rgba(248, 250, 252, 0.9));
            box-shadow:
                0 22px 46px rgba(2, 6, 23, 0.24),
                0 1px 0 rgba(255, 255, 255, 0.6) inset;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            overflow: hidden;
            animation: notification-permission-popup-in 220ms ease-out;
        }
        .notification-permission-popup::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #0d6efd, #38bdf8 60%, #22c55e);
        }
        .notification-permission-popup__title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            margin: 0;
            font-size: 15px;
            line-height: 1.3;
        }
        .notification-permission-popup__title i {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: #0d6efd;
            background: rgba(13, 110, 253, 0.12);
            box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.2);
        }
        .notification-permission-popup__body {
            margin: 10px 0 0;
            font-size: 13px;
            line-height: 1.6;
            color: #334155;
        }
        .notification-permission-popup__actions {
            margin-top: 14px;
            display: flex;
            gap: 8px;
        }
        .notification-permission-popup__btn {
            flex: 1;
            min-height: 38px;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 120ms ease, box-shadow 140ms ease, background-color 140ms ease, color 140ms ease;
            border: 1px solid transparent;
        }
        .notification-permission-popup__btn:focus-visible {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.35);
        }
        .notification-permission-popup__btn:active {
            transform: translateY(1px);
        }
        .notification-permission-popup__btn--primary {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: #ffffff;
            box-shadow: 0 10px 18px rgba(13, 110, 253, 0.32);
        }
        .notification-permission-popup__btn--primary:hover {
            box-shadow: 0 12px 22px rgba(13, 110, 253, 0.4);
            filter: brightness(1.03);
        }
        .notification-permission-popup__btn--ghost {
            background: rgba(241, 245, 249, 0.8);
            color: #334155;
            border-color: rgba(148, 163, 184, 0.36);
        }
        .notification-permission-popup__btn--ghost:hover {
            background: rgba(226, 232, 240, 0.95);
        }
        .notification-permission-popup__btn:disabled {
            cursor: not-allowed;
            opacity: 0.75;
            box-shadow: none;
            filter: none;
        }
        [data-theme="dark"] .notification-permission-popup {
            color: #e2e8f0;
            border-color: rgba(71, 85, 105, 0.55);
            background:
                linear-gradient(165deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.88));
            box-shadow:
                0 22px 46px rgba(0, 0, 0, 0.45),
                0 1px 0 rgba(148, 163, 184, 0.16) inset;
        }
        [data-theme="dark"] .notification-permission-popup__title i {
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.2);
            box-shadow: inset 0 0 0 1px rgba(96, 165, 250, 0.28);
        }
        [data-theme="dark"] .notification-permission-popup__body {
            color: #cbd5e1;
        }
        [data-theme="dark"] .notification-permission-popup__btn--ghost {
            background: rgba(51, 65, 85, 0.9);
            color: #dbeafe;
            border-color: rgba(100, 116, 139, 0.6);
        }
        [data-theme="dark"] .notification-permission-popup__btn--ghost:hover {
            background: rgba(71, 85, 105, 0.95);
        }
        @media (max-width: 540px) {
            .notification-permission-popup {
                left: 12px;
                right: 12px;
                bottom: calc(12px + env(safe-area-inset-bottom, 0px));
                width: auto;
                padding: 16px;
            }
            .notification-permission-popup__actions {
                flex-direction: column;
            }
            .notification-permission-popup__btn {
                width: 100%;
            }
        }
        @keyframes notification-permission-popup-in {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    `,document.head.appendChild(t)}function et(){document.getElementById(N)?.remove()}async function kt(t={}){const e=Z(t),n=P(Q,e),i=P(X,e);if(typeof Notification>"u")return"unsupported";if(Notification.permission==="granted")return"granted";if(Notification.permission==="denied")return"denied";let r="default";try{r=await Notification.requestPermission()}catch{r="denied"}return $(n,!0),r==="granted"&&($(i,!1),typeof t.onGranted=="function"&&await t.onGranted()),r}function nt(t={}){const e=Z(t),n=P(Q,e),i=P(X,e),r=t.force===!0;if(typeof Notification>"u"||Notification.permission!=="default"||!r&&(tt(n)||tt(i)))return!1;if(document.getElementById(N))return!0;Lt();const a=t.title||"Enable Push Notifications",o=t.message||"Stay updated with instant alerts and important updates.",s=t.enableLabel||"Enable",d=t.laterLabel||"Later",l=document.createElement("div");l.id=N,l.className="notification-permission-popup",l.innerHTML=`
        <div class="notification-permission-popup__title">
            <i class="bi bi-bell-fill text-primary"></i>
            <span>${g(a)}</span>
        </div>
        <p class="notification-permission-popup__body">${g(o)}</p>
        <div class="notification-permission-popup__actions">
            <button type="button" class="notification-permission-popup__btn notification-permission-popup__btn--primary" data-action="enable">${g(s)}</button>
            <button type="button" class="notification-permission-popup__btn notification-permission-popup__btn--ghost" data-action="later">${g(d)}</button>
        </div>
    `,document.body.appendChild(l);const c=()=>{$(i,!0),et()};l.querySelector('[data-action="later"]')?.addEventListener("click",c,{once:!0}),l.querySelector('[data-action="enable"]')?.addEventListener("click",async u=>{const m=u.currentTarget;m&&(m.disabled=!0),await kt({permissionScope:e,onGranted:t.onGranted}),et()},{once:!0});const f=Number.isFinite(t.autoHideMs)?t.autoHideMs:15e3;return f>0&&window.setTimeout(()=>{document.getElementById(N)&&c()},f),!0}function Pt(t){return[t.context||"default",t.bellSelector||"",t.listSelector||""].join("|")}function it(t,e){t&&(t.innerHTML=`
        <div class="text-center py-4 text-muted">
            <i class="bi bi-inbox fs-4"></i>
            <p class="mb-0 mt-2 small">${g(e)}</p>
        </div>
    `)}function ot(t,e,n){const i=Number.isFinite(n)?Math.max(0,n):0;e&&(e.textContent=String(i)),t&&t.classList.toggle("d-none",i<=0)}function Et(t,e){if(t){if(!Array.isArray(e)||e.length===0){it(t,"No new notifications");return}t.innerHTML=e.map(n=>{const i=Number.parseInt(n?.id,10)||0,r=g(n?.title||"Notification"),a=g(n?.message||""),o=g(St(n?.created_at)),s=J(n?.action_url),d=Number(n?.is_read)===1,l=d?"":"bg-light border-start border-primary border-2",c=s==="#"?"":` data-action-url="${g(s)}"`;return`
            <div class="notification-entry p-2 mb-2 rounded ${l}" data-notification-id="${i}"${c}>
                <div class="d-flex align-items-start gap-2">
                    <div class="flex-grow-1">
                        <div class="fw-semibold small mb-1">${r}</div>
                        <div class="small text-muted mb-1">${a}</div>
                        <div class="small text-secondary">${o}</div>
                    </div>
                    ${d?"":`<button type="button" class="btn btn-sm btn-outline-primary" data-action="mark-read" data-notification-id="${i}">Read</button>`}
                </div>
            </div>
        `}).join("")}}async function It(t=10){const e=await fetch(`/api/user-notifications?limit=${encodeURIComponent(t)}`,{credentials:"same-origin",headers:{Accept:"application/json"}});if(!e.ok)throw new Error(`Failed to load notifications (${e.status})`);const n=await e.json().catch(()=>({})),i=Array.isArray(n.notifications)?n.notifications:[],r=Number.isFinite(Number(n.unread_count))?Number(n.unread_count):i.filter(a=>Number(a?.is_read)!==1).length;return{notifications:i,unreadCount:r}}async function Mt(t){const e=await fetch("/api/notification/mark-read",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-CSRF-Token":vt()},body:JSON.stringify({notification_id:t})});return e.ok?(await e.json().catch(()=>({})))?.success!==!1:!1}function Ct(t={}){const e=t.context||"default",n=q.get(e);if(n?.promise)return n.promise;const i={initialized:!1,promise:null},r=t.requestPermissionOnLoad===!0,a=Number.isFinite(t.permissionPromptDelayMs)?t.permissionPromptDelayMs:Number.isFinite(t.bannerDelayMs)?t.bannerDelayMs:3e3,o=t.showPermissionPopup!==!1;return i.promise=(async()=>{try{const s=t.userId??T(),[{initFirebase:d},l]=await Promise.all([import("/assets/firebase/v2/dist/init.js"),import("/assets/firebase/v2/dist/messaging.js")]),{autoInitializeFCMToken:c,obtainAndSendFCMToken:f,autoInitializeForegroundListener:u,isMessagingSupported:m}=l,_=typeof m=="function"?await m()===!0:!0;if(xt(_,e),!_)return window.__fcmTokenObtained=!1,window.__requestFcmTokenSync=async()=>!1,window.__pendingFcmTokenSync&&(window.__pendingFcmTokenSync=!1),i.initialized=!0,!0;window.__requestFcmTokenSync=async(h={})=>{try{window.__fcmTokenObtained=!0;const v=h.userId??s;return await f({requestPermission:!1,userId:v||void 0,deviceId:h.deviceId}),!0}catch{return window.__fcmTokenObtained=!1,!1}},u(),c({userId:s,onSuccess:()=>{},onError:()=>{},autoRetry:!0});try{await d()}catch{}if(window.__pendingFcmTokenSync&&(window.__pendingFcmTokenSync=!1,window.__requestFcmTokenSync?.()),o){const h=t.permissionScope||e;b(()=>{setTimeout(()=>{nt({context:e,permissionScope:h,onGranted:async()=>{typeof window.__requestFcmTokenSync=="function"?await window.__requestFcmTokenSync():window.__pendingFcmTokenSync=!0},title:t.permissionTitle,message:t.permissionMessage,enableLabel:t.permissionEnableLabel,laterLabel:t.permissionLaterLabel})},a)})}if(r&&typeof Notification<"u"&&Notification.permission==="default")try{await Notification.requestPermission()}catch{}return i.initialized=!0,!0}catch{return q.delete(e),!1}})(),q.set(e,i),i.promise}function Ft(t={}){const e=Pt(t),n=D.get(e);n?.destroy&&n.destroy();const i=t.context||"default",r=Number.isFinite(t.pollIntervalMs)?t.pollIntervalMs:6e4,a=Number.isFinite(t.limit)?t.limit:10,o=L(t.bellSelector,"data-notification-bell"),s=L(t.badgeSelector,"data-notification-badge"),d=L(t.countSelector,"data-notification-count"),l=L(t.listSelector,"data-notification-list");if(o&&o.hasAttribute("data-bs-toggle")&&o.removeAttribute("data-bs-toggle"),!o||!l)return{active:!1};const c=Nt(o,l);c&&(c.classList.remove("show"),o.classList.remove("show"),o.closest(".dropdown")?.classList.remove("show"),o.setAttribute("aria-expanded","false"));const f=new AbortController,u={context:i,loading:!1,initialized:!1,pollId:null,destroy(){f.abort(),u.pollId&&(clearInterval(u.pollId),u.pollId=null),D.delete(e)}},m=async()=>{if(!u.loading){u.loading=!0;try{const p=await It(a);Et(l,p.notifications),ot(s,d,p.unreadCount),u.initialized=!0}catch{u.initialized||it(l,"Failed to load notifications"),ot(s,d,0)}finally{u.loading=!1}}},_=async p=>{const w=p.target.closest('[data-action="mark-read"]');if(w&&l.contains(w)){p.preventDefault(),p.stopPropagation();const Y=Number.parseInt(w.dataset.notificationId||"0",10);if(!Y)return;w.disabled=!0;const gt=await Mt(Y);w.disabled=!1,gt&&await m();return}const S=p.target.closest(".notification-entry[data-action-url]");if(!S||!l.contains(S))return;const j=J(S.dataset.actionUrl||"");j!=="#"&&(window.location.href=j)},h=p=>{const w=String(p?.detail?.kind||"");!(p?.detail?.open===!0)||w==="notification"||x()},v=()=>{c&&(c.style.zIndex="1079",o.style.zIndex="1081",c.classList.add("show"),o.classList.add("show"),o.closest(".dropdown")?.classList.add("show"),o.setAttribute("aria-expanded","true"),y("notification",!0))},x=()=>{if(!c)return;const p=c.classList.contains("show");c.classList.remove("show"),o.classList.remove("show"),o.closest(".dropdown")?.classList.remove("show"),o.setAttribute("aria-expanded","false"),k(c),c.style.zIndex="",o.style.zIndex="",p&&y("notification",!1)},ut=()=>{c&&c.classList.contains("show")?x():(v(),m(),c&&window.requestAnimationFrame(()=>{window.requestAnimationFrame(()=>{z(c,o)})}))},ft=p=>{p.preventDefault(),p.stopImmediatePropagation(),ut()},pt=()=>{m(),c&&window.requestAnimationFrame(()=>{window.requestAnimationFrame(()=>{z(c,o)})})},mt=()=>{c&&k(c)},W=()=>{!c||!c.classList.contains("show")||z(c,o)};l.addEventListener("click",_,{signal:f.signal}),o.addEventListener("click",ft,{signal:f.signal}),o.removeEventListener("shown.bs.dropdown",pt),o.removeEventListener("hidden.bs.dropdown",mt),window.addEventListener("resize",W,{signal:f.signal}),window.addEventListener("scroll",W,{passive:!0,signal:f.signal});const wt=p=>{if(!(!c||!o)&&c.classList.contains("show")){const w=p.target;if(w instanceof Element&&(o.contains(w)||c.contains(w)))return;x()}},bt=p=>{p.key==="Escape"&&x()};return document.addEventListener("click",wt,{signal:f.signal}),document.addEventListener("keydown",bt,{signal:f.signal}),document.addEventListener(O,h,{signal:f.signal}),b(()=>{m()}),u.pollId=window.setInterval(m,r),D.set(e,u),{active:!0,destroy:u.destroy}}async function Tt(){try{await Ct({context:A,permissionScope:A,requestPermissionOnLoad:!1,userId:T(),permissionTitle:"Enable Push Notifications",permissionMessage:"Stay updated with instant alerts and important updates.",permissionEnableLabel:"Enable",permissionLaterLabel:"Later",showPermissionPopup:yt.permissionPopupEnabled!==!1}),b(()=>{Ft({context:A,bellSelector:"#broxNotificationBell",badgeSelector:"#broxNotificationBadge",countSelector:"#broxNotificationCount",listSelector:"#broxNotificationsList"})})}catch{}}function E(){return window.__fcmMessagingSupported===!1}function U(){const t=document.getElementById("enableNotificationsBtn");t&&(t.style.display="none")}async function I(){if(!E())try{typeof window.__requestFcmTokenSync=="function"?await window.__requestFcmTokenSync():window.__pendingFcmTokenSync=!0}catch{}}async function At(){return E()?(U(),!1):"Notification"in window?Notification.permission==="granted"?(await I(),!0):Notification.permission==="denied"?!1:!!nt({context:"public",permissionScope:"public",force:!0,title:"Enable Push Notifications",message:"Stay updated with instant alerts and important updates.",enableLabel:"Enable",laterLabel:"Later",onGranted:async()=>{await I()}}):(window.__FC_DEBUG&&console.warn("[Notifications] Not supported in this browser"),!1)}function Ot(){const t=document.getElementById("enableNotificationsBtn");if(E()){U();return}if(!("Notification"in window)){t&&(t.style.display="none");return}t&&t.addEventListener("click",async()=>{await At()}),!G&&window.__FC_DEBUG&&console.log("[Notifications] Checking notification status..."),Notification.permission==="denied"?t&&(t.style.display="block"):Notification.permission==="granted"?(t&&(t.style.display="none"),I()):Notification.permission==="default"&&t&&(t.style.display="none")}ht?(window.__pendingFcmTokenSync=!1,window.__requestFcmTokenSync=async()=>!1,window.__fcmTokenObtained=!1):(Tt(),b(Ot),window.addEventListener("fcm-support-resolved",t=>{t?.detail?.supported===!1&&U()}),document.addEventListener("firebase-initialized",async()=>{E()||(window.__FC_DEBUG&&console.log("[Notifications] Firebase initialized, checking notification status..."),"Notification"in window&&Notification.permission==="granted"&&await I())})),document.addEventListener("DOMContentLoaded",function(){const t=window.location.pathname;document.querySelectorAll(".brox-nav-link").forEach(n=>{const i=n.getAttribute("href");(i===t||i!=="/"&&t.startsWith(i))&&n.classList.add("brox-active")})}),document.addEventListener("click",function(t){const e=document.getElementById("broxMainNav"),n=document.querySelector(".brox-mobile-toggle");if(e&&n){const i=e.contains(t.target),r=n.contains(t.target);if(!i&&!r&&e.classList.contains("show")){const a=new bootstrap.Collapse(e,{toggle:!0})}}});function M(t){t&&(t.style.removeProperty("position"),t.style.removeProperty("left"),t.style.removeProperty("top"),t.style.removeProperty("right"),t.style.removeProperty("bottom"),t.style.removeProperty("inset"),t.style.removeProperty("transform"),t.style.removeProperty("z-index"))}function y(t,e){try{document.dispatchEvent(new CustomEvent(e?O:_t,{detail:{kind:t,open:!!e,timestamp:Date.now()}}))}catch{}}function rt(t,e){if(!t||!e)return;if(window.getComputedStyle(t).position==="static"){M(t);return}const i=8,r=window.innerWidth||document.documentElement.clientWidth,a=window.innerHeight||document.documentElement.clientHeight,o=t.getBoundingClientRect();if(!(o.left<i||o.right>r-i||o.top<i||o.bottom>a-i)){M(t);return}const d=e.getBoundingClientRect(),l=Math.min(o.width||t.offsetWidth||320,Math.max(180,r-i*2)),c=Math.min(o.height||t.offsetHeight||360,Math.max(160,a-i*2));let f=d.right-l;f=Math.max(i,Math.min(f,r-l-i));let u=d.bottom+8;if(u+c>a-i){const m=d.top-c-8;u=m>=i?m:Math.max(i,a-c-i)}t.style.position="fixed",t.style.left=`${Math.round(f)}px`,t.style.top=`${Math.round(u)}px`,t.style.right="auto",t.style.bottom="auto",t.style.inset="auto",t.style.transform="none",t.style.zIndex="1080"}function st(t){return t&&t.closest(".dropdown")?.querySelector(".dropdown-menu")||null}function qt(){const t=document.querySelector(".brox-navbar-container");if(!t)return;const e=Array.from(t.querySelectorAll('.dropdown-toggle[data-bs-toggle="dropdown"], [data-notification-bell][data-bs-toggle="dropdown"]'));if(!e.length)return;const n=()=>{e.forEach(i=>{const r=st(i);!r||!r.classList.contains("show")||rt(r,i)})};e.forEach(i=>{const r=st(i);if(!r)return;const a=()=>{window.requestAnimationFrame(()=>{window.requestAnimationFrame(()=>{rt(r,i)})})},o=()=>{M(r)};i.addEventListener("shown.bs.dropdown",a),i.addEventListener("hidden.bs.dropdown",o)}),window.addEventListener("resize",n),window.addEventListener("scroll",n,{passive:!0})}document.querySelectorAll('.brox-nav-link[href^="#"]').forEach(t=>{t.addEventListener("click",function(e){const n=this.getAttribute("href");if(n!=="#"&&n!==""){e.preventDefault();const i=document.querySelector(n);i&&i.scrollIntoView({behavior:"smooth",block:"start"})}})});function Dt(){const t=document.querySelector(".brox-navbar-container");if(!t)return;const e=i=>{t.classList.toggle("is-scrolled",!!i)};if("IntersectionObserver"in window){let i=document.querySelector("[data-brox-scroll-sentinel]");i||(i=document.createElement("span"),i.setAttribute("data-brox-scroll-sentinel","1"),i.setAttribute("aria-hidden","true"),i.style.cssText="position:absolute;top:0;left:0;width:1px;height:1px;pointer-events:none;",document.body.prepend(i)),new IntersectionObserver(a=>{const o=a[0];e(!o.isIntersecting)},{root:null,threshold:[0],rootMargin:"-8px 0px 0px 0px"}).observe(i);return}const n=()=>{const i=window.pageYOffset||document.documentElement.scrollTop||0;e(i>8)};window.addEventListener("scroll",n,{passive:!0}),n()}function Rt(){const t=document.getElementById("broxNavbarUser");if(!t)return;const e=t.closest(".dropdown"),n=e?.querySelector(".dropdown-menu");if(!e||!n)return;const i=()=>window.matchMedia("(max-width: 991.98px)").matches,r=()=>n.classList.contains("show"),a=s=>{const d=r();n.classList.toggle("show",s),t.classList.toggle("show",s),e.classList.toggle("show",s),t.setAttribute("aria-expanded",s?"true":"false"),s||M(n),s!==d&&y("user",s)},o=s=>{const d=String(s?.detail?.kind||"");if(!(!(s?.detail?.open===!0)||d==="user")&&r()){if(!i()&&window.bootstrap?.Dropdown?.getOrCreateInstance){const c=window.bootstrap.Dropdown.getOrCreateInstance(t);if(c&&typeof c.hide=="function"){c.hide();return}}a(!1)}};t.addEventListener("click",s=>{i()&&(s.preventDefault(),s.stopPropagation(),a(!r()))}),n.addEventListener("click",s=>{if(!i())return;s.target instanceof Element&&s.target.closest(".dropdown-item, .brox-dropdown-item")&&a(!1)}),t.addEventListener("shown.bs.dropdown",()=>{i()||y("user",!0)}),t.addEventListener("hidden.bs.dropdown",()=>{i()||y("user",!1)}),document.addEventListener(O,o),document.addEventListener("click",s=>{if(!i()||!r())return;const d=s.target;d instanceof Element&&(t.contains(d)||n.contains(d)||a(!1))}),document.addEventListener("keydown",s=>{s.key==="Escape"&&r()&&a(!1)}),window.addEventListener("resize",()=>{!i()&&r()&&a(!1)})}b(Dt),b(qt),b(Rt);let C=null;const at=async()=>(C||(C=import(withAssetVersion("/assets/firebase/v2/dist/notification-system.js")).catch(t=>{throw C=null,t})),C);window.BroxNavbar={loadNotifications:async function(...t){try{const e=await at();if(typeof e.loadUserNotifications=="function")return e.loadUserNotifications(...t);if(typeof e.broxLoadNotifications=="function")return e.broxLoadNotifications(...t)}catch{}return Promise.resolve(null)},markNotificationRead:async function(t,...e){try{const n=await at();if(typeof n.markNotificationAsRead=="function")return n.markNotificationAsRead(t,...e);if(typeof n.broxMarkNotificationRead=="function")return n.broxMarkNotificationRead(t,...e)}catch{}return Promise.resolve(!1)}};const zt=['[id^="post_carousel_"]','[id^="page_carousel_"]','[id^="tag_carousel_"]','[id^="category_carousel_"]','[id^="related_post_carousel_"]','[id^="related_page_carousel_"]','[id^="related_mobile_carousel_"]'].join(","),H={interval:5e3,wrap:!0,keyboard:!0,pause:"hover",touch:!0};function Bt(t){if(!t||t.dataset.carouselInitialized==="true"||!(window.bootstrap&&typeof window.bootstrap.Carousel=="function"))return;const e={...H,interval:t.dataset.interval?Number(t.dataset.interval):H.interval,pause:t.dataset.pause??H.pause,wrap:t.dataset.wrap!=="false",keyboard:t.dataset.keyboard!=="false",touch:t.dataset.touch!=="false"};try{new window.bootstrap.Carousel(t,e),t.dataset.carouselInitialized="true"}catch{}}function $t(){let n=0;const i=(a=document)=>{if(!(window.bootstrap&&typeof window.bootstrap.Carousel=="function")){n<5&&(n+=1,setTimeout(()=>i(a),800));return}const o=a.querySelectorAll?a.querySelectorAll(zt):[];o.length&&requestAnimationFrame(()=>{o.forEach(s=>Bt(s))})},r=new MutationObserver(a=>{a.forEach(o=>{o.addedNodes.forEach(s=>{s&&s.nodeType===1&&i(s)})})});b(()=>{i(),document.body&&r.observe(document.body,{childList:!0,subtree:!0})}),window.reinitializeCarousels=i}class F{constructor(e,n={}){this.element=e,this.target=parseInt(e?.dataset?.target||"0",10)||0,this.current=0,this.duration=Number(n.duration||2e3),this.decimals=Number(n.decimals||0),this.prefix=n.prefix||"",this.suffix=n.suffix||"",this.separator=n.separator??",",this.animated=!1}easeOutQuad(e){return e<.5?2*e*e:-1+(4-2*e)*e}formatNumber(e){let n=this.decimals>0?Number(e).toFixed(this.decimals):Math.floor(e).toString();return this.decimals>0&&(n=parseFloat(n).toString()),this.separator!==""&&(n=n.replace(/\B(?=(\d{3})+(?!\d))/g,this.separator)),`${this.prefix}${n}${this.suffix}`}start(){if(!this.element||this.animated)return;this.animated=!0;const e=performance.now(),n=this.current,i=r=>{const a=r-e,o=Math.min(a/this.duration,1),s=this.easeOutQuad(o);this.current=n+(this.target-n)*s,this.element.textContent=this.formatNumber(this.current),o<1?requestAnimationFrame(i):(this.current=this.target,this.element.textContent=this.formatNumber(this.target))};requestAnimationFrame(i)}}function ct(t=".counter",e={}){const n=document.querySelectorAll(t);if(!n.length)return;const i=new IntersectionObserver((r,a)=>{r.forEach(o=>{if(!o.isIntersecting||o.target.dataset.animating==="true")return;o.target.dataset.animating="true",new F(o.target,e).start(),a.unobserve(o.target)})},{threshold:.5});n.forEach(r=>i.observe(r))}async function V(t="/api/statistics"){try{const e=await fetch(t);if(!e.ok)throw new Error(`Failed to fetch statistics (${e.status})`);return await e.json()}catch{return null}}function dt(t,e){const n=document.querySelector(t);if(!n)return;n.dataset.target=String(e),new F(n).start()}async function lt(t="/api/statistics"){const e=await V(t);e&&typeof e=="object"&&Object.entries(e).forEach(([n,i])=>{dt(`[data-stat="${n}"]`,i)}),setInterval(async()=>{const n=await V(t);!n||typeof n!="object"||Object.entries(n).forEach(([i,r])=>{const a=`[data-stat="${i}"]`,o=document.querySelector(a);if(!o)return;const s=parseInt(o.dataset.target||"0",10);if(s===Number(r))return;o.dataset.target=String(r);const d=new F(o);d.current=s,d.target=Number(r),d.animated=!1,d.start()})},3e4)}b(()=>{$t(),document.querySelector("[data-stat]")?lt():ct()}),window.CounterAnimation=F,window.initializeCounters=ct,window.fetchStatistics=V,window.updateCounterValue=dt,window.initializeRealtimeCounters=lt;

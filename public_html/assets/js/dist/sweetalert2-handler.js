(function(){"use strict";const e={toastPosition:"top-right",toastDuration:4e3,animationDuration:400,successColor:"#198754",dangerColor:"#dc3545",warningColor:"#ffc107",infoColor:"#0dcaf0",primaryColor:"#0d6efd",announceToasts:!0,closeOnEscape:!0,allowHtml:!1,showConfirmButton:!0,showCancelButton:!1};function m(o){const t={success:e.successColor,danger:e.dangerColor,error:e.dangerColor,warning:e.warningColor,info:e.infoColor,primary:e.primaryColor};return t[o]||t.info}function f(o){return{success:"success",danger:"error",error:"error",warning:"warning",info:"info",primary:"info"}[o]||"info"}function w(o={},t=[]){const n={},i={};return Object.entries(o||{}).forEach(([r,s])=>{t.includes(r)?n[r]=s:i[r]=s}),{custom:n,swalOptions:i}}window.showMessage=async function(o,t="info",n=e.toastDuration,i={}){if(!o)return;const{custom:r,swalOptions:s}=w(i,["allowHtml"]),a=r.allowHtml===!0,l=String(o),u=f(t),c={toast:!0,position:e.toastPosition,icon:u,title:a?void 0:l,html:a?l:void 0,text:void 0,showConfirmButton:!1,showCloseButton:!0,closeButtonAriaLabel:"Close",timer:n>0?n:void 0,timerProgressBar:n>0,didOpen:async d=>{e.announceToasts&&p(`${t}: ${o}`)},customClass:{container:"swal2-message-container",popup:`swal2-popup-${t}`,title:"swal2-message-title"},...s};return await Swal.fire(c)},window.showToast=function(o,t="info",n=e.toastDuration,i={}){return window.showMessage(o,t,n,i)},window.showAlert=async function(o,t="Alert",n="info",i={}){if(!o)return;const{custom:r,swalOptions:s}=w(i,["allowHtml"]),a=r.allowHtml===!0,u={icon:f(n),title:String(t),html:a?String(o):void 0,text:a?void 0:String(o),confirmButtonText:"OK",confirmButtonColor:m(n),allowOutsideClick:!1,allowEscapeKey:e.closeOnEscape,position:"center",didOpen:async c=>{e.announceToasts&&p(`Alert: ${t}. ${o}`)},customClass:{popup:`swal2-alert-${n}`,title:"swal2-alert-title",confirmButton:"swal2-button-confirm"},...s};return await Swal.fire(u)},window.showConfirm=async function(o,t="Confirm",n="warning",i={}){if(!o)return!1;const{custom:r,swalOptions:s}=w(i,["allowHtml"]),a=r.allowHtml===!0,u={icon:f(n),title:String(t),html:a?String(o):void 0,text:a?void 0:String(o),showCancelButton:!0,confirmButtonText:"Yes, Proceed",confirmButtonColor:m(n),cancelButtonText:"Cancel",cancelButtonColor:"#6c757d",allowOutsideClick:!1,allowEscapeKey:e.closeOnEscape,position:"center",didOpen:async d=>{e.announceToasts&&p(`Confirmation required: ${t}. ${o}`)},customClass:{popup:`swal2-confirm-${n}`,title:"swal2-confirm-title",confirmButton:"swal2-button-confirm",cancelButton:"swal2-button-cancel"},...s};return(await Swal.fire(u)).isConfirmed??!1},window.showPrompt=async function(o,t="",n="",i={}){if(!o)return null;const{custom:r,swalOptions:s}=w(i,["required"]),a=r.required===!0,l={title:String(n||"Please Enter"),html:String(o),input:"text",inputValue:String(t),inputAttributes:{"aria-label":String(n||o),placeholder:String(t||"Enter text...")},showCancelButton:!0,confirmButtonText:"Submit",confirmButtonColor:e.primaryColor,cancelButtonText:"Cancel",cancelButtonColor:"#6c757d",allowOutsideClick:!1,allowEscapeKey:e.closeOnEscape,position:"center",inputValidator:c=>{if(!c&&a)return"This field is required"},didOpen:async c=>{const d=c.querySelector("input");d&&(d.focus(),d.select()),e.announceToasts&&p(`Prompt: ${o}`)},customClass:{popup:"swal2-prompt",title:"swal2-prompt-title",input:"swal2-prompt-input",confirmButton:"swal2-button-confirm",cancelButton:"swal2-button-cancel"},...s};return(await Swal.fire(l)).value??null},window.showValidationErrors=function(o=[]){!Array.isArray(o)||o.length===0||o.forEach((t,n)=>{setTimeout(()=>{window.showMessage(t,"danger",5e3)},n*300)})},window.handleAjaxSuccess=function(o,t=""){const n=t||o.message||"Success",i=o.status||"success";window.showMessage(n,i,5e3)},window.handleAjaxError=function(o,t=""){const n=t||o.message||"An error occurred. Please try again.";window.showMessage(n,"danger",5e3)};function p(o){if(!o)return;const t=document.getElementById("sr-live-region")||b();t.setAttribute("aria-live","assertive"),t.setAttribute("aria-atomic","true"),t.textContent=o,setTimeout(()=>{t.textContent=""},2e3)}function b(){const o=document.createElement("div");return o.id="sr-live-region",o.className="visually-hidden",o.setAttribute("role","status"),o.setAttribute("aria-live","polite"),document.body.appendChild(o),o}window.MessageHandlerConfig={set(o,t){o in e?e[o]=t:console.warn(`Unknown config key: ${o}`)},get(o){return e[o]},setAll(o){Object.assign(e,o)},getAll(){return{...e}}},window.MessageHandler={init:function(o=null){o&&typeof o=="object"&&MessageHandlerConfig.setAll(o);const t=Array.isArray(window.__INITIAL_FLASH_QUEUE)?window.__INITIAL_FLASH_QUEUE.slice():[];if(t.length===0&&window.__INITIAL_FLASH&&t.push(window.__INITIAL_FLASH),t.length>0){let n=!1;t.forEach((i,r)=>{if(!i||!i.text&&!i.message)return;const s=i.text||i.message,a=i.status||i.type||"info",l=i.duration||e.toastDuration;setTimeout(()=>{window.showMessage(s,a,l)},r*160),n=!0}),n&&(window.__FLASH_RENDERED_ON_LOAD=!0)}delete window.__INITIAL_FLASH,delete window.__INITIAL_FLASH_QUEUE,console.log("\u2705 SweetAlert2 Message Handler v3.0 initialized")},getConfig:function(){return MessageHandlerConfig.getAll()},setConfig:function(o,t){typeof o=="object"?MessageHandlerConfig.setAll(o):MessageHandlerConfig.set(o,t)},showLoading:async function(o="Loading...",t=""){return await Swal.fire({icon:"info",title:t,html:o,allowOutsideClick:!1,allowEscapeKey:!1,didOpen:()=>{Swal.showLoading()}})},hideLoading:function(){Swal.hideLoading(),Swal.close()}},document.readyState==="loading"?document.addEventListener("DOMContentLoaded",function(){window.__MESSAGE_HANDLER_INITIALIZED||(window.__MESSAGE_HANDLER_INITIALIZED=!0,window.MessageHandler.init())}):window.__MESSAGE_HANDLER_INITIALIZED||(window.__MESSAGE_HANDLER_INITIALIZED=!0,window.MessageHandler.init());const g=document.createElement("style");g.textContent=`
        /* SweetAlert2 Toast Customization */
        .swal2-popup.swal2-toast {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 1rem 1.5rem;
        }

        .swal2-popup.swal2-toast.swal2-popup-success {
            border-left: 4px solid #198754;
        }

        .swal2-popup.swal2-toast.swal2-popup-danger,
        .swal2-popup.swal2-toast.swal2-popup-error {
            border-left: 4px solid #dc3545;
        }

        .swal2-popup.swal2-toast.swal2-popup-warning {
            border-left: 4px solid #ffc107;
        }

        .swal2-popup.swal2-toast.swal2-popup-info {
            border-left: 4px solid #0dcaf0;
        }

        .swal2-message-title {
            font-size: 1rem;
            font-weight: 500;
            color: #212529;
        }

        /* SweetAlert2 Alert & Dialog Customization */
        .swal2-popup:not(.swal2-toast) {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            padding: 2rem;
        }

        .swal2-alert-title,
        .swal2-confirm-title,
        .swal2-prompt-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 1rem;
        }

        .swal2-popup.swal2-alert-success {
            border-top: 5px solid #198754;
        }

        .swal2-popup.swal2-alert-danger,
        .swal2-popup.swal2-alert-error {
            border-top: 5px solid #dc3545;
        }

        .swal2-popup.swal2-alert-warning {
            border-top: 5px solid #ffc107;
        }

        .swal2-popup.swal2-alert-info {
            border-top: 5px solid #0dcaf0;
        }

        /* Buttons */
        .swal2-button-confirm {
            border-radius: 8px;
            padding: 0.75rem 2rem !important;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .swal2-button-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .swal2-button-cancel {
            border-radius: 8px;
            padding: 0.75rem 2rem !important;
            font-weight: 600;
            font-size: 0.95rem;
            background: #e9ecef !important;
            color: #495057 !important;
        }

        .swal2-button-cancel:hover {
            background: #dee2e6 !important;
        }

        /* Input Styling */
        .swal2-prompt-input {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .swal2-prompt-input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 107, 253, 0.25);
        }

        /* Icon Customization */
        .swal2-icon {
            border-radius: 50%;
            border: 4px solid;
            animation: show-icon 0.5s ease-in-out;
        }

        .swal2-icon.swal2-success {
            border-color: #198754;
            color: #198754;
        }

        .swal2-icon.swal2-error {
            border-color: #dc3545;
            color: #dc3545;
        }

        .swal2-icon.swal2-warning {
            border-color: #ffc107;
            color: #ffc107;
        }

        .swal2-icon.swal2-info {
            border-color: #0dcaf0;
            color: #0dcaf0;
        }

        @keyframes show-icon {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Accessibility */
        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }

        /* Animation Support for prefers-reduced-motion */
        @media (prefers-reduced-motion: reduce) {
            .swal2-popup,
            .swal2-icon,
            .swal2-button-confirm,
            .swal2-button-cancel {
                animation: none !important;
                transition: none !important;
            }
        }
    `,document.head.appendChild(g)})();

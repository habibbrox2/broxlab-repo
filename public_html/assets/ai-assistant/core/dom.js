export function getEl(id) {
  return document.getElementById(id);
}

export function scrollToBottom(container) {
  if (!container) return;
  container.scrollTop = container.scrollHeight;
}

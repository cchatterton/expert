/* Purpose-specific forms. Model output is always rendered as text. */
(() => {
 'use strict';
 const config = window.expertConfig;
 async function request(route, values) {
  const response = await fetch(config.api + route, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce}, body: JSON.stringify(values)});
  const result = await response.json();
  if (!response.ok) throw new Error(result.message || config.failed);
  return result;
 }
 function renderSources(container, sources) {
  for (const source of sources) {
   const article = document.createElement('article'); article.className = 'expert-result';
   const link = document.createElement('a'); link.href = source.url; link.textContent = source.title;
   const detail = document.createElement('p'); detail.textContent = source.type.replace('expert_', '') + (Array.isArray(source.taxonomy) && source.taxonomy.length ? ' · ' + source.taxonomy.join(', ') : '');
   const text = document.createElement('p'); text.textContent = source.text.slice(0, 300);
   article.append(link, detail, text); container.append(article);
  }
 }
 async function submitForm(form) {
  const button = form.querySelector('button[type="submit"]'); const status = form.querySelector('.expert-status'); const output = form.querySelector('.expert-results');
  if (button.disabled) return;
  button.disabled = true; form.setAttribute('aria-busy', 'true'); status.textContent = config.working;
  try {
   const values = Object.fromEntries(new FormData(form)); if (values.id) values.id = Number(values.id);
   const result = await request(form.dataset.expertRoute, values);
   output.replaceChildren();
   if (result.saved) {
    status.textContent = config.saved; form.querySelector('[name="modified"]').value = result.modified;
   } else {
    if (result.answer) {const answer = document.createElement('p'); answer.className = 'expert-answer'; answer.textContent = result.answer; output.append(answer);}
    const sources = result.sources || result.results || []; renderSources(output, sources);
    status.textContent = !result.answer && !sources.length ? config.empty : '';
   }
  } catch (error) {status.textContent = error.message || config.failed;}
  finally {button.disabled = false; form.removeAttribute('aria-busy');}
 }
 document.addEventListener('submit', event => {
  const form = event.target.closest('.expert-query'); if (!form) return;
  event.preventDefault(); submitForm(form);
 });
 if (config.objectId) request('engagement', {id: config.objectId}).catch(() => { /* Nonessential aggregate; do not interrupt reading. */ });
})();

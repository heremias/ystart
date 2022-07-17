document.addEventListener('DOMContentLoaded', function () {
  const root = document.getElementById('decoupled-page-root');
  const text = document.createElement('p');
  text.innerText = 'Attached. Waiting...';
  text.classList.add('decoupled-pages-example-text');
  root.appendChild(text);
  setTimeout(function () {
    text.innerText = 'It worked!';
  }, 2000);
});

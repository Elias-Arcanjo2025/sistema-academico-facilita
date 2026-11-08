/**
 * Bootstrap & Axios são carregados via CDN no layout (app.blade.php).
 * Este ficheiro serve apenas para configurações adicionais.
 */
if (window.axios) {
    window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
}

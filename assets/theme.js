window.tailwind = window.tailwind || {};
window.tailwind.config = { ...window.tailwind.config, darkMode: 'class' };

try {
    const theme = localStorage.getItem('nexora-theme');
    document.documentElement.classList.toggle('dark', theme !== 'light');
} catch (error) {
    document.documentElement.classList.add('dark');
}
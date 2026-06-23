import { ref, watch } from 'vue';

const isDark = ref(localStorage.getItem('theme') === 'dark');

function apply(dark: boolean) {
    document.documentElement.classList.toggle('dark', dark);
}

apply(isDark.value);

watch(isDark, (val) => {
    apply(val);
    localStorage.setItem('theme', val ? 'dark' : 'light');
});

export function useDarkMode() {
    return {
        isDark,
        toggle: () => { isDark.value = !isDark.value; },
    };
}

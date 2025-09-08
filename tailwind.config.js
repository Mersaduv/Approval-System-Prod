import { defineConfig } from '@tailwindcss/postcss'

export default defineConfig({
  content: [
    "./resources/**/*.js",
    "./resources/**/*.jsx",
    "./resources/**/*.blade.php",
    "./resources/**/*.vue",
  ],
})

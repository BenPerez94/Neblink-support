export default {
  content: ['./templates/**/*.html.twig', './assets/**/*.js'],
  theme: {
    extend: {
      colors: {
        'sage-dark': '#3f6b56',
        'sage': '#5c8a6f',
        'sage-light': '#8ba888',
      },
      fontFamily: {
        heading: ['Sora', 'sans-serif'],
        sans: ['Inter', 'sans-serif'],
      },
    },
  },
}
const path = require('path');

module.exports = {
  content: [
    path.join(__dirname, 'resources/views/**/*.blade.php'),
    path.join(__dirname, 'resources/js/**/*.{js,ts,jsx,tsx}'),
  ],
  theme: {
    extend: {
      colors: {
        emerald: {
          600: '#059669',
          700: '#047857',
          800: '#065f46',
        },
      },
    },
  },
  plugins: [],
};

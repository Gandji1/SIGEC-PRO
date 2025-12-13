import tailwindcss from "tailwindcss";
import typography from "@tailwindcss/typography";

export default {
  content: ["./index.html", "./src/**/*.{js,ts,jsx,tsx}"],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Palette moderne SIGEC
        brand: {
          50: "#EEF2FF",
          100: "#E0E7FF",
          200: "#C7D2FE",
          300: "#A5B4FC",
          400: "#818CF8",
          500: "#3F5EFB",  // Bleu moderne principal
          600: "#4F46E5",
          700: "#4338CA",
          800: "#3730A3",
          900: "#312E81",
        },
        accent: {
          50: "#ECFEFF",
          100: "#CFFAFE",
          200: "#A5F3FC",
          300: "#67E8F9",
          400: "#22D3EE",
          500: "#00B4D8",  // Bleu clair secondaire
          600: "#0891B2",
          700: "#0E7490",
          800: "#155E75",
          900: "#164E63",
        },
        success: {
          50: "#ECFDF5",
          100: "#D1FAE5",
          200: "#A7F3D0",
          300: "#6EE7B7",
          400: "#34D399",
          500: "#2EC4B6",  // Vert modernisé
          600: "#059669",
          700: "#047857",
          800: "#065F46",
          900: "#064E3B",
        },
        // Mode sombre optimisé
        dark: {
          bg: "#121212",       // Fond principal sombre
          card: "#1E1E1E",     // Fond des cartes
          secondary: "#2A2A2A", // Fond secondaire
          border: "#333333",   // Bordures
          text: "#F1F1F1",     // Texte clair
        },
        // Mode clair optimisé
        light: {
          bg: "#F7F7FA",       // Fond gris très doux
          card: "#FFFFFF",     // Cartes blanches
          border: "#E6E6E9",   // Bordures douces
        },
      },
      maxWidth: {
        'card': '800px',       // Largeur max des cartes
        'content': '1200px',   // Largeur max du contenu
      },
      boxShadow: {
        'card': '0px 2px 7px rgba(0,0,0,0.06)',
        'card-hover': '0px 4px 12px rgba(0,0,0,0.1)',
        'dark-card': '0px 2px 7px rgba(0,0,0,0.3)',
      },
      animation: {
        'fade-in': 'fadeIn 0.3s ease-out',
        'scale-in': 'scaleIn 0.2s ease-out',
        'slide-up': 'slideUp 0.3s ease-out',
        'slide-down': 'slideDown 0.3s ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        scaleIn: {
          '0%': { opacity: '0', transform: 'scale(0.95)' },
          '100%': { opacity: '1', transform: 'scale(1)' },
        },
        slideUp: {
          '0%': { opacity: '0', transform: 'translateY(10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        slideDown: {
          '0%': { opacity: '0', transform: 'translateY(-10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
    },
  },
  plugins: [typography],
};


import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.jsx",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Inter", "Arial", ...defaultTheme.fontFamily.sans],
                display: ["Georgia", "Times New Roman", "serif"],
            },
            colors: {
                primary: {
                    50: "#f3f7ee", 100: "#e8efdf", 200: "#d0dfbf",
                    300: "#b1c99a", 400: "#9cbb83", 500: "#8cae76",
                    600: "#66864f", 700: "#486338", 800: "#3a502e", 900: "#2d3e25",
                },
                secondary: {
                    50: "#f6f7f2", 100: "#eceee3", 200: "#d9ddcd", 300: "#b8c2a5",
                    400: "#91a17c", 500: "#71835d", 600: "#576b45", 700: "#405332",
                    800: "#304027", 900: "#202d1b",
                },
                accent: {
                    50: "#fbfaf5", 100: "#f3f1e7", 200: "#e7e6db", 300: "#d6d1bc",
                    400: "#c0b89d", 500: "#a59b7b", 600: "#877e60", 700: "#6b624a",
                    800: "#534d3d", 900: "#3e3a30",
                },
                success: {
                    50: "#e8f8f0",
                    100: "#d1f1e1",
                    200: "#a3e3c3",
                    300: "#75d5a5",
                    400: "#47c787",
                    500: "#2ecc71",
                    600: "#25a35a",
                    700: "#1c7a44",
                    800: "#12522d",
                    900: "#092917",
                },
                warning: {
                    50: "#fef6e6",
                    100: "#fdedcc",
                    200: "#fbdb99",
                    300: "#f9c966",
                    400: "#f7b733",
                    500: "#f39c12",
                    600: "#c27d0e",
                    700: "#925e0b",
                    800: "#613e07",
                    900: "#311f04",
                },
                error: {
                    50: "#fee6e6",
                    100: "#fdcccc",
                    200: "#fb9999",
                    300: "#f96666",
                    400: "#f73333",
                    500: "#e74c3c",
                    600: "#b93d30",
                    700: "#8b2e24",
                    800: "#5c1e18",
                    900: "#2e0f0c",
                },
                neutral: {
                    50: "#faf9f4",
                    100: "#f3f1e7",
                    200: "#e7e6db",
                    300: "#d8d9cb",
                    400: "#b7bfac",
                    500: "#73806e",
                    600: "#586456",
                    700: "#414e40",
                    800: "#303d31",
                    900: "#202b21",
                },
            },
            boxShadow: {
                glass: "0 8px 32px 0 rgba(31, 38, 135, 0.15)",
                glow: "0 0 20px rgba(255, 107, 53, 0.3)",
            },
            backdropBlur: {
                xs: "2px",
            },
            animation: {
                "fade-in": "fadeIn 0.3s ease-in",
                "slide-up": "slideUp 0.3s ease-out",
                "scale-in": "scaleIn 0.2s ease-out",
                "pulse-slow": "pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite",
            },
            keyframes: {
                fadeIn: {
                    "0%": { opacity: "0" },
                    "100%": { opacity: "1" },
                },
                slideUp: {
                    "0%": { transform: "translateY(10px)", opacity: "0" },
                    "100%": { transform: "translateY(0)", opacity: "1" },
                },
                scaleIn: {
                    "0%": { transform: "scale(0.95)", opacity: "0" },
                    "100%": { transform: "scale(1)", opacity: "1" },
                },
            },
        },
    },

    plugins: [forms],
};

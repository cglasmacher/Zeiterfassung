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
                display: ["Poppins", ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: "#f5f6ef",
                    100: "#e9ecdc",
                    200: "#d5ddbd",
                    300: "#b9c595",
                    400: "#9eae74",
                    500: "#7b8e54",
                    600: "#60713e",
                    700: "#4b5933",
                    800: "#3f492e",
                    900: "#353e29",
                },
                secondary: {
                    50: "#f2f5f4",
                    100: "#e3e9e6",
                    200: "#c8d2cd",
                    300: "#a3b5aa",
                    400: "#7a9384",
                    500: "#597464",
                    600: "#425b4c",
                    700: "#35483e",
                    800: "#2b3b33",
                    900: "#202c25",
                },
                accent: {
                    50: "#faf7f0",
                    100: "#f2ead9",
                    200: "#e4d4b5",
                    300: "#d3b98a",
                    400: "#c5a16a",
                    500: "#ae834b",
                    600: "#92683d",
                    700: "#755033",
                    800: "#62432f",
                    900: "#533b2d",
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
                    50: "#f8f9fa",
                    100: "#f1f3f5",
                    200: "#e9ecef",
                    300: "#dee2e6",
                    400: "#ced4da",
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

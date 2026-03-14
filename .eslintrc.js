module.exports = {
  extends: [
    "plugin:@wordpress/eslint-plugin/recommended",
    "plugin:@wordpress/eslint-plugin/esnext",
    "plugin:@wordpress/eslint-plugin/i18n",
  ],
  env: {
    browser: true,
    es6: true,
    node: true,
    jquery: true,
  },
  globals: {
    wp: "readonly",
    Razorpay: "readonly",
    wcRazorpayOAuth: "readonly",
    wcRazorpayReceiptConfig: "readonly",
    ajaxurl: "readonly",
    console: "readonly",
  },
  parserOptions: {
    ecmaVersion: 2020,
    sourceType: "module",
  },
  rules: {
    // Custom rules for this project
    "max-len": ["error", { code: 200 }],
    "no-console": "warn",
    "no-debugger": "error",
    "prefer-const": "error",
    "no-var": "error",
    "object-shorthand": "error",
    "prefer-arrow-callback": "error",
    "arrow-spacing": "error",
    "prefer-template": "error",

    // WordPress specific
    "@wordpress/no-unused-vars-before-return": "error",
    "@wordpress/valid-sprintf": "error",
    "@wordpress/i18n-text-domain": [
      "error",
      {
        allowedTextDomain: "wc-gocardless-payments",
      },
    ],
    "@wordpress/i18n-translator-comments": "error",
    "@wordpress/i18n-no-variables": "error",
    "@wordpress/i18n-no-placeholders-only": "error",
    "@wordpress/i18n-ellipsis": "error",
  },
};

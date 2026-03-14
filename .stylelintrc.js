module.exports = {
  extends: [
    "@wordpress/stylelint-config/scss",
    "stylelint-config-recess-order",
    "stylelint-config-prettier-scss",
  ],
  plugins: ["stylelint-order", "stylelint-scss"],
  rules: {
    "at-rule-empty-line-before": [
      "always",
      {
        except: ["blockless-after-same-name-blockless", "first-nested"],
        ignore: ["after-comment"],
        ignoreAtRules: ["else", "include"],
      },
    ],
    "at-rule-no-unknown": null,
    "scss/at-rule-no-unknown": [
      true,
      {
        ignoreAtRules: ["apply", "theme", "source", "variant", "plugin"],
      },
    ],
    "color-named": [
      "never",
      {
        ignore: ["inside-function"],
      },
    ],
    "declaration-property-unit-allowed-list": null,
    "font-weight-notation": null,
    "no-descending-specificity": null,
    "property-no-unknown": [
      true,
      {
        ignoreProperties: ["composes"],
      },
    ],
    "rule-empty-line-before": [
      "always-multi-line",
      {
        except: ["first-nested"],
        ignore: ["after-comment"],
      },
    ],
    "scss/comment-no-empty": null,
    "scss/dollar-variable-colon-space-after": "always-single-line",
    "scss/dollar-variable-colon-space-before": "never",
    "scss/dollar-variable-no-missing-interpolation": null,
    "scss/double-slash-comment-whitespace-inside": "always",
    "selector-class-pattern": null,
    "selector-id-pattern": null,
    "value-keyword-case": [
      "lower",
      {
        ignoreProperties: ["font-family"],
      },
    ],
  },
  ignoreFiles: [
    "node_modules/**/*",
    "build/**/*",
    "vendor/**/*",
    "legacy/**/*",
    "spa/public/css/**/*.min.css",
    "dist/**/*",
  ],
};

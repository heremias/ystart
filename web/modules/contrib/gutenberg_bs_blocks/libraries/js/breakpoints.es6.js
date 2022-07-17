const breakpoints = ["Xs", "Sm", "Md", "Lg", "Xl"];
const labels = [
  Drupal.t("Extra small devices"),
  Drupal.t("Small devices"),
  Drupal.t("Medium devices"),
  Drupal.t("Large devices"),
  Drupal.t("Extra large devices")
];

export default breakpoints;

export { labels };

export const getInfix = breakpoint => {
  return breakpoint !== "Xs" ? "-" + breakpoint.toLowerCase() : "";
};

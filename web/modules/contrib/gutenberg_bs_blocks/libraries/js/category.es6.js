const { select, dispatch } = wp.data;

const category = {
  slug: "bootstrap",
  title: Drupal.t("Bootstrap")
};

const currentCategories = select("core/blocks")
  .getCategories()
  .filter(item => item.slug !== category.slug);
dispatch("core/blocks").setCategories([category, ...currentCategories]);

export default category;

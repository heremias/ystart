import category from "category.es6";
import attributes from "container/config/attributes.es6";
import supports from "container/config/supports.es6";
import transforms from "container/config/transforms.es6";
import BlockEdit from "container/components/BlockEdit.es6";
import BlockSave from "container/components/BlockSave.es6";

const { registerBlockType } = wp.blocks;

registerBlockType(category.slug + "/container", {
  title: Drupal.t("Container"),
  description: Drupal.t("Bootstrap Container Block"),
  icon: "layout",
  category: category.slug,
  attributes,
  supports,
  transforms,
  edit: BlockEdit,
  save: BlockSave
});

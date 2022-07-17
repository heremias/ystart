const getEditWrapperProps = attributes => {
  const { horizontalAlignment, verticalAlignment } = attributes;

  return {
    "data-horizontal-alignment": horizontalAlignment,
    "data-vertical-alignment": verticalAlignment
  };
};

export default getEditWrapperProps;

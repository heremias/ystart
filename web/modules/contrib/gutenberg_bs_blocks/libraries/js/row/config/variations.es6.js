const { SVG, Path } = wp.components;

const variations = [
  {
    name: "two-columns-equal",
    title: Drupal.t("50 / 50"),
    description: Drupal.t("Two columns; equal split"),
    icon: (
      <SVG
        width="48"
        height="48"
        viewBox="0 0 48 48"
        xmlns="http://www.w3.org/2000/svg"
      >
        <Path
          fillRule="evenodd"
          clipRule="evenodd"
          d="M39 12C40.1046 12 41 12.8954 41 14V34C41 35.1046 40.1046 36 39 36H9C7.89543 36 7 35.1046 7 34V14C7 12.8954 7.89543 12 9 12H39ZM39 34V14H25V34H39ZM23 34H9V14H23V34Z"
        />
      </SVG>
    ),
    isDefault: true,
    innerBlocks: [["bootstrap/column"], ["bootstrap/column"]],
    scope: ["block"]
  },
  {
    name: "two-columns-one-third-two-thirds",
    title: Drupal.t("30 / 70"),
    description: Drupal.t("Two columns; one-third, two-thirds split"),
    icon: (
      <SVG
        width="48"
        height="48"
        viewBox="0 0 48 48"
        xmlns="http://www.w3.org/2000/svg"
      >
        <Path
          fillRule="evenodd"
          clipRule="evenodd"
          d="M39 12C40.1046 12 41 12.8954 41 14V34C41 35.1046 40.1046 36 39 36H9C7.89543 36 7 35.1046 7 34V14C7 12.8954 7.89543 12 9 12H39ZM39 34V14H20V34H39ZM18 34H9V14H18V34Z"
        />
      </SVG>
    ),
    innerBlocks: [
      ["bootstrap/column", { sizeMd: 4 }],
      ["bootstrap/column", { sizeMd: 8 }]
    ],
    scope: ["block"]
  },
  {
    name: "two-columns-two-thirds-one-third",
    title: Drupal.t("70 / 30"),
    description: Drupal.t("Two columns; two-thirds, one-third split"),
    icon: (
      <SVG
        width="48"
        height="48"
        viewBox="0 0 48 48"
        xmlns="http://www.w3.org/2000/svg"
      >
        <Path
          fillRule="evenodd"
          clipRule="evenodd"
          d="M39 12C40.1046 12 41 12.8954 41 14V34C41 35.1046 40.1046 36 39 36H9C7.89543 36 7 35.1046 7 34V14C7 12.8954 7.89543 12 9 12H39ZM39 34V14H30V34H39ZM28 34H9V14H28V34Z"
        />
      </SVG>
    ),
    innerBlocks: [
      ["bootstrap/column", { sizeMd: 8 }],
      ["bootstrap/column", { sizeMd: 4 }]
    ],
    scope: ["block"]
  },
  {
    name: "three-columns-equal",
    title: Drupal.t("33 / 33 / 33"),
    description: Drupal.t("Three columns; equal split"),
    icon: (
      <SVG
        width="48"
        height="48"
        viewBox="0 0 48 48"
        xmlns="http://www.w3.org/2000/svg"
      >
        <Path
          fillRule="evenodd"
          d="M41 14a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v20a2 2 0 0 0 2 2h30a2 2 0 0 0 2-2V14zM28.5 34h-9V14h9v20zm2 0V14H39v20h-8.5zm-13 0H9V14h8.5v20z"
        />
      </SVG>
    ),
    innerBlocks: [
      ["bootstrap/column"],
      ["bootstrap/column"],
      ["bootstrap/column"]
    ],
    scope: ["block"]
  },
  {
    name: "three-columns-wider-center",
    title: Drupal.t("25 / 50 / 25"),
    description: Drupal.t("Three columns; wide center column"),
    icon: (
      <SVG
        width="48"
        height="48"
        viewBox="0 0 48 48"
        xmlns="http://www.w3.org/2000/svg"
      >
        <Path
          fillRule="evenodd"
          d="M41 14a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v20a2 2 0 0 0 2 2h30a2 2 0 0 0 2-2V14zM31 34H17V14h14v20zm2 0V14h6v20h-6zm-18 0H9V14h6v20z"
        />
      </SVG>
    ),
    innerBlocks: [
      ["bootstrap/column", { sizeMd: 3 }],
      ["bootstrap/column", { sizeMd: 6 }],
      ["bootstrap/column", { sizeMd: 3 }]
    ],
    scope: ["block"]
  }
];

export default variations;

document.querySelectorAll("[data-copy-clipboard]").forEach((element) => {
	element.addEventListener("click", () => {
		const textToCopy = element.getAttribute("data-copy-clipboard");

		if (textToCopy !== null) {
			navigator.clipboard.writeText(textToCopy);
		}
	});
});

document.querySelectorAll("[data-select-on-click]").forEach((element) => {
	element.addEventListener("focus", (event) => {
		const target = event.target as HTMLInputElement;
		target.select();
	});
});

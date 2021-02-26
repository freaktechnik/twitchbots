const editButtons = document.querySelectorAll(".submission-edit");
const editor = document.querySelector("#editor form");
const channelField = editor.querySelector("#channel");
const typeField = editor.querySelector("#type");
const idField = editor.querySelector('input[name="id"]');

const updateEditor = (e) => {
    const currentRow = e.target.closest('tr');
    channelField.value = currentRow.cells[2].textContent;
    if(currentRow.cells[1].childElementCount > 0) {
        const link = currentRow.cells[1].querySelector("a");
        if(link) {
            typeField.value = link.title;
        }
    }
    else {
        typeField.value = currentRow.cells[1].textContent;
    }
    idField.value = currentRow.querySelector('input[name="id"]').value;
    document.querySelector('#editor .channel-name').textContent = currentRow.cells[0].textContent;
};
for(const button of editButtons) {
    button.addEventListener("click", updateEditor, false);
}

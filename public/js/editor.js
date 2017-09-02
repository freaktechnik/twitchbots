require(['bootstrap', 'jquery'], function() {
    const editButtons = document.querySelectorAll(".submission-edit");
    const editor = document.querySelector("#editor form");
    const channelField = editor.querySelector("#channel");
    const typeField = editor.querySelector("#type");
    const idField = editor.querySelector('input[name="id"]');

    let currentRow;
    const updateEditor = (e) => {
        currentRow = e.target.parentNode.parentNode;
        channelField.value = currentRow.cells[2].textContent;
        typeField.value = currentRow.cells[1].textContent;
        idField.value = currentRow.querySelector('input[name="id"]').value;
        document.querySelector('#editor .channel-name').textContent = currentRow.cells[0].textContent;
    };
    const save = (e) => {
        e.preventDefault();

        currentRow.cells[2].textContent = channel;
        currentRow.cells[1].textContent = description;

        const body = new FormData(editor);

        fetch('/lib/subedit', {
            method: 'POST',
            body,
            credentials: 'same-origin'
        }).then((r) => {
            if(!r.ok) {
                throw r.status;
            }
            else {
                $('#editor').modal('hide');
            }
        }).catch(console.error);
    };

    editor.addEventListener("submit", save, false);
    for(const button of editButtons) {
        button.addEventListener("click", updateEditor, false);
    }
});

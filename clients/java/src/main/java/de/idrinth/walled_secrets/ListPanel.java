package de.idrinth.walled_secrets;

import java.awt.BorderLayout;
import java.awt.Panel;
import java.awt.event.MouseEvent;
import java.awt.event.MouseListener;
import java.io.IOException;
import java.io.UnsupportedEncodingException;
import java.time.Instant;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import javax.json.JsonObject;
import javax.json.JsonValue;
import javax.swing.JLabel;
import javax.swing.JOptionPane;
import javax.swing.JPanel;
import javax.swing.JPasswordField;
import javax.swing.JScrollPane;
import javax.swing.JTree;
import javax.swing.event.TreeModelEvent;
import javax.swing.event.TreeModelListener;
import javax.swing.tree.TreeModel;
import javax.swing.tree.TreePath;

public class ListPanel extends Panel
{
    private final ScheduledExecutorService executor = Executors.newSingleThreadScheduledExecutor();
    public ListPanel(SwingFactory factory, Config config, Request request) {
        super();
        this.setLayout(new BorderLayout());
        JTree list = new JTree();
        list.addMouseListener(new MouseListener () {
            @Override
            public void mouseClicked(MouseEvent e) {
                Object obj = list.getLastSelectedPathComponent();
                if(obj!=null && obj.getClass().equals(Secret.class)) {
                    Secret s = (Secret) obj;
                    JPanel panel = new JPanel();
                    JLabel label = new JLabel("Enter your Master-Password:");
                    JPasswordField pass = new JPasswordField(10);
                    panel.add(label);
                    panel.add(pass);
                    String[] options = new String[]{"OK", "Cancel"};
                    int option = JOptionPane.showOptionDialog(
                        null,
                        panel,
                        "Master-Password",
                        JOptionPane.NO_OPTION,
                        JOptionPane.PLAIN_MESSAGE,
                        null,
                        options,
                        options[1]
                    );
                    if(option == 1) {
                        return;
                    }
                    switch (s.type) {
                        case "login":
                            factory.showLoginDetail(s.id, new String(pass.getPassword()));
                            return;
                        case "note":
                            factory.showNoteDetail(s.id, new String(pass.getPassword()));
                            return;
                        default:
                            return;
                    }
                }
            }
            @Override
            public void mousePressed(MouseEvent e) {}
            @Override
            public void mouseReleased(MouseEvent e) {}
            @Override
            public void mouseEntered(MouseEvent e) {}
            @Override
            public void mouseExited(MouseEvent e) {}
        });
        TreeData data = new TreeData();
        list.setModel(data);
        executor.scheduleAtFixedRate(new DataUpdater(config, request, data), 5, 1, TimeUnit.SECONDS);
        add(new JScrollPane(list));
    }

    private static class Folder {
        public final String name;
        public final String organisation;
        public final List<Secret> secrets;
        public Folder(String name, List<Secret> secrets, String organisation)
        {
            this.name = name;
            this.secrets = secrets;
            this.organisation = organisation;
        }
        public String toString(){
            if (!organisation.isEmpty()) {
                return "(" + secrets.size() + ")" + name + " ("+organisation+")";
            }
            return name;
        }
    }

    private static class Secret {
        public final String type;
        public final String name;
        public final String id;
        public Secret(String type, String name, String id) {
            this.type = type;
            this.name = name;
            this.id = id;
        }
        public String toString(){
            return "["+type+"] "+name;
        }
    }
    private static class DataUpdater implements Runnable {
            private Instant last;
            private Instant updated;
            private final Config config;
            private final Request request;
            private final TreeData data;
            public DataUpdater(Config config, Request request, TreeData data) {
                this.config = config;
                this.request = request;
                this.data = data;
                this.last = Instant.MIN;
                this.updated = Instant.MIN;
            }
            @Override
            public void run() {
                if (config.getEmail().isEmpty()) {
                    return;
                }
                if (last.getEpochSecond() < Instant.now().getEpochSecond() - config.getFrequency()) {
                    last = Instant.now();
                    try {
                        data.from(request.getSecretList(updated));
                        updated = Instant.now();
                    } catch (IOException e) {
                        System.err.println(e.getMessage());
                    }
                }
            }
    }
    private static class TreeData implements TreeModel {
        private final List<TreeModelListener> listeners = new ArrayList<>();
        private List<Folder> folders = new ArrayList<>();
        private final static String ROOT = "Folders";
        @Override
        public Object getRoot() {
            return ROOT;
        }
        public void from(JsonObject obj) {
            List<Folder> list = new ArrayList<>();
            for (Map.Entry<String, JsonValue> set : obj.entrySet()) {
                JsonObject folder = set.getValue().asJsonObject();
                List<Secret> secrets = new ArrayList<>();
                for (JsonValue v : folder.getJsonArray("notes")) {
                    secrets.add(new Secret("note", v.asJsonObject().getString("public"), v.asJsonObject().getString("id")));
                }
                for (JsonValue v : folder.getJsonArray("logins")) {
                    secrets.add(new Secret("login", v.asJsonObject().getString("public"), v.asJsonObject().getString("id")));
                }
                list.add(new Folder(folder.getString("name"), secrets, folder.getString("organisation", "")));
            }
            this.folders = list;
            for (TreeModelListener listener: listeners) {
                listener.treeStructureChanged(new TreeModelEvent (this, new String[]{ROOT}));
            }
        }
        @Override
        public Object getChild(Object parent, int index) {
            if (parent.equals(ROOT)) {
                return folders.get(index);
            }
            if (parent.getClass().equals(Folder.class)) {
                return ((Folder) parent).secrets.get(index);
            }
            return null;
        }

        @Override
        public int getChildCount(Object parent) {
            if (parent.equals(ROOT)) {
                return folders.size();
            }
            if (parent.getClass().equals(Folder.class)) {
                return ((Folder) parent).secrets.size();
            }
            return 0;
        }

        @Override
        public boolean isLeaf(Object node) {
            return node.getClass().equals(Secret.class);
        }

        @Override
        public void valueForPathChanged(TreePath path, Object newValue) {}

        @Override
        public int getIndexOfChild(Object parent, Object child) {
            if (parent.equals(ROOT)) {
                return folders.indexOf(child);
            }
            if (parent.getClass().equals(Folder.class)) {
                return ((Folder) parent).secrets.indexOf(child);
            }
            return -1;
        }

        @Override
        public void addTreeModelListener(TreeModelListener l) {
            listeners.add(l);
        }

        @Override
        public void removeTreeModelListener(TreeModelListener l) {
            listeners.remove(l);
        }
    }
}
